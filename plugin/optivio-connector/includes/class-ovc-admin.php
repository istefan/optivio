<?php
/**
 * Admin screen: Settings → OptiVio Connector.
 *
 * @package   OptiVio_Connector
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin module.
 */
final class OVC_Admin {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_ovc_action', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Adds the settings page.
	 *
	 * @return void
	 */
	public static function menu() {
		add_options_page( 'OptiVio Connector', 'OptiVio Connector', 'manage_options', 'optivio-connector', array( __CLASS__, 'render' ) );
	}

	/**
	 * Handles admin actions (nonce + capability checked).
	 *
	 * @return void
	 */
	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acces interzis.', 'optivio-connector' ), 403 );
		}
		check_admin_referer( 'ovc_action' );

		$action  = isset( $_POST['ovc_do'] ) ? sanitize_key( wp_unslash( $_POST['ovc_do'] ) ) : '';
		$message = 'ok';

		switch ( $action ) {
			case 'save':
				$current = get_option( 'ovc_connection', array() );
				$current = is_array( $current ) ? $current : array();
				$api     = isset( $_POST['api_base'] ) ? esc_url_raw( wp_unslash( $_POST['api_base'] ), array( 'http', 'https' ) ) : '';
				$key     = isset( $_POST['key_id'] ) ? sanitize_text_field( wp_unslash( $_POST['key_id'] ) ) : '';
				$secret  = isset( $_POST['secret'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['secret'] ) ) ) : '';
				update_option(
					'ovc_connection',
					array(
						'api_base' => $api,
						'key_id'   => $key,
						'secret'   => '' !== $secret ? $secret : ( $current['secret'] ?? '' ),
					),
					false
				);
				OVC_Settings::flush();
				$message = 'saved';
				break;
			case 'heartbeat':
				$result  = OVC_Heartbeat::send();
				$message = 200 === $result['code'] ? 'heartbeat_ok' : 'heartbeat_fail';
				break;
			case 'sync':
				OVC_Content::start_full_sync();
				$message = 'sync';
				break;
			case 'flush':
				OVC_Queue::flush();
				$message = 'flushed';
				break;
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'optivio-connector', 'ovc_msg' => $message ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Renders the page.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$c        = OVC_Settings::connection();
		$status   = get_option( 'ovc_status', array() );
		$counts   = OVC_Queue::counts();
		$cursor   = get_option( 'ovc_sync_cursor', array() );
		$last     = get_option( 'ovc_last_error', array() );
		$messages = array(
			'saved'          => 'Setările au fost salvate.',
			'heartbeat_ok'   => 'Conexiune reușită cu OptiVio.',
			'heartbeat_fail' => 'Conexiunea a eșuat — verificați URL-ul, cheia și secretul.',
			'sync'           => 'Sincronizarea completă a conținutului a fost pornită.',
			'flushed'        => 'Coada a fost procesată.',
		);
		$msg      = isset( $_GET['ovc_msg'] ) ? sanitize_key( wp_unslash( $_GET['ovc_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap">
			<h1>OptiVio Connector</h1>
			<?php if ( isset( $messages[ $msg ] ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( 'heartbeat_fail' === $msg ? 'error' : 'success' ); ?> is-dismissible"><p><?php echo esc_html( $messages[ $msg ] ); ?></p></div>
			<?php endif; ?>

			<h2>Stare</h2>
			<table class="widefat striped" style="max-width:760px">
				<tbody>
					<tr><td>Configurat</td><td><?php echo esc_html( OVC_Settings::is_configured() ? 'da' : 'nu' ); ?></td></tr>
					<tr><td>Ultimul heartbeat</td><td><?php echo esc_html( ! empty( $status['time'] ) ? wp_date( 'd.m.Y H:i:s', (int) $status['time'] ) . ' — HTTP ' . (int) $status['code'] : '—' ); ?></td></tr>
					<tr><td>Coadă evenimente / conținut</td><td><?php echo esc_html( $counts['events'] . ' / ' . $counts['content'] ); ?></td></tr>
					<tr><td>Sincronizare conținut</td><td><?php echo esc_html( ! empty( $cursor['phase'] ) ? $cursor['phase'] : '—' ); ?> <?php echo esc_html( get_option( 'ovc_last_full_sync' ) ? '(ultima completă: ' . wp_date( 'd.m.Y H:i', (int) get_option( 'ovc_last_full_sync' ) ) . ')' : '' ); ?></td></tr>
					<tr><td>Ultima eroare de trimitere</td><td><?php echo esc_html( ! empty( $last['time'] ) ? wp_date( 'd.m.Y H:i', (int) $last['time'] ) . ' — HTTP ' . (int) $last['code'] . ' ' . $last['error'] : '—' ); ?></td></tr>
					<tr><td>Yoast SEO / Gravity Forms</td><td><?php echo esc_html( ( defined( 'WPSEO_VERSION' ) ? 'activ' : 'inactiv' ) . ' / ' . ( class_exists( 'GFForms' ) ? 'activ' : 'inactiv' ) ); ?></td></tr>
				</tbody>
			</table>

			<p>
				<?php foreach ( array( 'heartbeat' => 'Testează conexiunea', 'sync' => 'Sincronizare completă conținut', 'flush' => 'Trimite coada acum' ) as $do => $label ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:6px">
						<?php wp_nonce_field( 'ovc_action' ); ?>
						<input type="hidden" name="action" value="ovc_action">
						<input type="hidden" name="ovc_do" value="<?php echo esc_attr( $do ); ?>">
						<?php submit_button( $label, 'secondary', 'submit', false ); ?>
					</form>
				<?php endforeach; ?>
			</p>

			<h2>Conexiune</h2>
			<p>Valorile sunt incluse automat în arhiva descărcată din OptiVio. Completați aici doar dacă ați rotit secretul sau mutați aplicația.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ovc_action' ); ?>
				<input type="hidden" name="action" value="ovc_action">
				<input type="hidden" name="ovc_do" value="save">
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="ovc_api">URL OptiVio</label></th><td><input class="regular-text" id="ovc_api" name="api_base" value="<?php echo esc_attr( $c['api_base'] ); ?>"></td></tr>
					<tr><th scope="row"><label for="ovc_key">Key ID</label></th><td><input class="regular-text" id="ovc_key" name="key_id" value="<?php echo esc_attr( $c['key_id'] ); ?>"></td></tr>
					<tr><th scope="row"><label for="ovc_secret">Secret</label></th><td><input class="regular-text" type="password" id="ovc_secret" name="secret" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( '' !== $c['secret'] ? '•••••••• (lăsați gol pentru a-l păstra)' : '' ); ?>"></td></tr>
				</table>
				<?php submit_button( 'Salvează' ); ?>
			</form>
		</div>
		<?php
	}
}
