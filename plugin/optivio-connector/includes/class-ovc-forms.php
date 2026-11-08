<?php
/**
 * Gravity Forms integration: plugin-level anti-spam (honeypot, minimum fill time, blocked
 * patterns) + reporting of every entry (spam included, flagged) to OptiVio.
 *
 * @package   OptiVio_Connector
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Forms module.
 */
final class OVC_Forms {

	/**
	 * Flags computed during validation, keyed by form id.
	 *
	 * @var array<int,array>
	 */
	private static $flags = array();

	/**
	 * Field types never reported.
	 */
	const SKIP_TYPES = array( 'honeypot', 'html', 'section', 'page', 'captcha', 'password', 'creditcard' );

	/**
	 * Hooks (only when Gravity Forms is active).
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'gform_get_form_filter', array( __CLASS__, 'inject_fields' ), 10, 2 );
		add_filter( 'gform_entry_is_spam', array( __CLASS__, 'is_spam' ), 10, 3 );
		add_action( 'gform_entry_created', array( __CLASS__, 'report' ), 10, 2 );
	}

	/**
	 * Adds the honeypot and a signed render timestamp to every form.
	 *
	 * @param string $html Form HTML.
	 * @param array  $form Form.
	 * @return string
	 */
	public static function inject_fields( $html, $form ) {
		$time   = time();
		$token  = $time . '.' . substr( hash_hmac( 'sha256', $time . '|' . (int) $form['id'], wp_salt( 'nonce' ) ), 0, 16 );
		$fields = '<div style="position:absolute!important;left:-9999px!important;height:1px;overflow:hidden" aria-hidden="true">'
			. '<label>Website<input type="text" name="ovc_website" value="" tabindex="-1" autocomplete="off"></label></div>'
			. '<input type="hidden" name="ovc_ts" value="' . esc_attr( $token ) . '">';
		return str_replace( '</form>', $fields . '</form>', $html );
	}

	/**
	 * Deterministic plugin-level spam check.
	 *
	 * @param bool  $is_spam Current verdict.
	 * @param array $form    Form.
	 * @param array $entry   Entry.
	 * @return bool
	 */
	public static function is_spam( $is_spam, $form, $entry ) {
		$flags                            = self::compute_flags( $form, $entry );
		self::$flags[ (int) $form['id'] ] = $flags;
		$config                           = OVC_Settings::remote_config();

		if ( $flags['honeypot'] || $flags['blocked_patterns'] > 0 ) {
			return true;
		}
		if ( null !== $flags['fill_seconds'] && $flags['fill_seconds'] < ( (int) $config['min_fill_seconds'] ) / 2 ) {
			return true;
		}
		return (bool) $is_spam;
	}

	/**
	 * Reports the entry to OptiVio (queued).
	 *
	 * @param array $entry Entry.
	 * @param array $form  Form.
	 * @return void
	 */
	public static function report( $entry, $form ) {
		if ( ! OVC_Settings::is_configured() ) {
			return;
		}
		$flags = self::$flags[ (int) $form['id'] ] ?? self::compute_flags( $form, $entry );
		$ua    = OVC_Settings::user_agent();

		OVC_Queue::push(
			'events',
			array(
				'type'        => 'form_entry',
				'entry_id'    => (int) $entry['id'],
				'form_id'     => (int) $form['id'],
				'form_title'  => (string) $form['title'],
				'occurred_at' => gmdate( 'c', strtotime( (string) ( $entry['date_created'] ?? 'now' ) . ' UTC' ) ?: time() ),
				'fields'      => self::fields( $form, $entry ),
				'page_url'    => esc_url_raw( (string) ( $entry['source_url'] ?? '' ) ),
				'user_agent'  => $ua,
				'device'      => OVC_Attribution::device( $ua ),
				'ip'          => OVC_Settings::visitor_ip(),
				'flags'       => array_merge( $flags, array( 'gf_spam' => 'spam' === ( $entry['status'] ?? '' ) ) ),
				'attribution' => OVC_Attribution::current(),
			)
		);
	}

	/**
	 * Builds label => value for reportable fields.
	 *
	 * @param array $form  Form.
	 * @param array $entry Entry.
	 * @return array<string,string>
	 */
	public static function fields( $form, $entry ) {
		$fields = array();
		foreach ( (array) ( $form['fields'] ?? array() ) as $field ) {
			$type = is_object( $field ) ? (string) $field->type : (string) ( $field['type'] ?? '' );
			if ( in_array( $type, self::SKIP_TYPES, true ) ) {
				continue;
			}
			$id    = is_object( $field ) ? $field->id : ( $field['id'] ?? 0 );
			$label = is_object( $field ) ? (string) ( $field->adminLabel ?: $field->label ) : (string) ( $field['label'] ?? '' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( is_object( $field ) && method_exists( $field, 'get_value_export' ) ) {
				$value = $field->get_value_export( $entry, (string) $id, true );
			} else {
				$value = $entry[ (string) $id ] ?? '';
			}
			if ( '' === $label ) {
				$label = 'Câmp ' . $id;
			}
			$fields[ substr( wp_strip_all_tags( $label ), 0, 120 ) ] = substr( wp_strip_all_tags( (string) $value ), 0, 5000 );
		}
		return $fields;
	}

	/**
	 * Computes plugin-level spam flags from the submitted request.
	 *
	 * @param array $form  Form.
	 * @param array $entry Entry.
	 * @return array{honeypot:bool,fill_seconds:float|null,blocked_patterns:int}
	 */
	private static function compute_flags( $form, $entry ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Gravity Forms validates its own submission; we only read our extra fields.
		$honeypot = isset( $_POST['ovc_website'] ) && '' !== trim( sanitize_text_field( wp_unslash( $_POST['ovc_website'] ) ) );
		$token    = isset( $_POST['ovc_ts'] ) ? sanitize_text_field( wp_unslash( $_POST['ovc_ts'] ) ) : '';
		// phpcs:enable

		$fill = null;
		if ( preg_match( '/^(\d{10})\.([a-f0-9]{16})$/', $token, $m ) ) {
			$expected = substr( hash_hmac( 'sha256', $m[1] . '|' . (int) $form['id'], wp_salt( 'nonce' ) ), 0, 16 );
			if ( hash_equals( $expected, $m[2] ) ) {
				$fill = max( 0, time() - (int) $m[1] );
			}
		}

		$text    = strtolower( implode( ' ', self::fields( $form, $entry ) ) );
		$blocked = 0;
		foreach ( (array) OVC_Settings::remote_config()['blocked_words'] as $word ) {
			$word = strtolower( trim( (string) $word ) );
			if ( '' !== $word && false !== strpos( $text, $word ) ) {
				++$blocked;
			}
		}

		return array(
			'honeypot'         => $honeypot,
			'fill_seconds'     => $fill,
			'blocked_patterns' => $blocked,
		);
	}
}
