<?php
/**
 * OptiVio — Audit trail of sensitive actions.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Security;

use OptiVio\Core\Database;
use OptiVio\Core\Logger;
use OptiVio\Core\Request;
use Throwable;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Writes rows into audit_logs. Never pass secrets in $meta (they are stripped defensively anyway).
 */
final class Audit_Log {

	/**
	 * Human labels for known actions.
	 */
	public const LABELS = array(
		'auth.login'              => 'Autentificare reușită',
		'auth.login_failed'       => 'Autentificare eșuată',
		'auth.login_throttled'    => 'Autentificare blocată (rate limit)',
		'auth.logout'             => 'Deconectare',
		'auth.session_expired'    => 'Sesiune expirată',
		'auth.password_changed'   => 'Parolă schimbată',
		'setup.super_admin'       => 'Instalare: Super Admin creat',
		'user.create'             => 'Utilizator creat',
		'user.update'             => 'Utilizator actualizat',
		'user.access_changed'     => 'Acces afaceri modificat',
		'user.password_reset'    => 'Parolă resetată de admin',
		'business.create'         => 'Afacere creată',
		'business.update'         => 'Afacere actualizată',
		'business.usp_update'     => 'USP actualizat',
		'business.ai_profiles'    => 'Profiluri AI client actualizate',
		'business.repuvio_link'   => 'Legătură RepuVio modificată',
		'business_contact.create' => 'Contact adăugat',
		'business_contact.update' => 'Contact actualizat',
		'business_contact.delete' => 'Contact șters',
		'business_offering.create' => 'Serviciu adăugat',
		'business_offering.update' => 'Serviciu actualizat',
		'business_offering.toggle' => 'Serviciu activat/dezactivat',
		'business_offering.delete' => 'Serviciu șters',
		'business_rule.create'    => 'Regulă adăugată',
		'business_rule.toggle'    => 'Regulă activată/dezactivată',
		'business_rule.delete'    => 'Regulă ștearsă',
		'site.create'             => 'Site adăugat',
		'site.update'             => 'Site actualizat',
		'site.connector_rotate'   => 'Secret conector generat',
		'site.connector_revoke'   => 'Conector revocat',
		'site.integration_update' => 'Integrare site actualizată',
		'site.plugin_download'    => 'Plugin OptiVio Connector descărcat',
		'remote.apply'            => 'Modificare aplicată pe site',
		'google.connect'          => 'Cont Google conectat',
		'engine.run'              => 'Analiză deterministă recalculată',
		'ai.analysis'             => 'Analiză AI rulată',
		'ai.analysis_failed'      => 'Analiză AI eșuată',
		'ai.card_content'         => 'Conținut card generat cu AI',
		'card.edit'               => 'Action Card editat',
		'card.apply'              => 'Action Card aprobat și aplicat',
		'card.apply_failed'       => 'Aplicare Action Card eșuată',
		'card.manual'             => 'Action Card aplicat manual',
		'card.reject'             => 'Action Card respins (regulă nouă)',
		'card.rollback'           => 'Rollback Action Card',
		'card.image_upload'       => 'Imagine încărcată pentru card',
		'keyword.add'             => 'Cuvânt cheie adăugat',
		'insight.dismiss'         => 'Recomandare ignorată',
		'competitor.add'          => 'Concurent adăugat',
		'competitor.discover'     => 'Descoperire concurenți (SERP)',
		'google.disconnect'       => 'Cont Google deconectat',
		'google.sync_manual'      => 'Sincronizare Google manuală',
		'remote.failed'           => 'Modificare eșuată pe site',
		'remote.rollback'         => 'Rollback modificare site',
		'call.create'             => 'Apel înregistrat',
		'call.update'             => 'Apel actualizat',
		'call.delete'             => 'Apel șters',
		'call.import'             => 'Import apeluri CSV',
		'form.spam_override'      => 'Clasificare spam modificată manual',
		'settings.update'         => 'Setări actualizate',
		'credential.save'         => 'Cheie API salvată',
		'credential.delete'       => 'Cheie API ștearsă',
		'jobs.run_manual'         => 'Rulare manuală coadă joburi',
		'context.business_switch' => 'Comutare afacere activă',
	);

	/**
	 * Records an audit entry.
	 *
	 * @param string      $action      Action key (e.g. "user.create").
	 * @param string|null $entity_type Entity type.
	 * @param int|null    $entity_id   Entity id.
	 * @param array       $meta        Context (no secrets).
	 * @param int|null    $business_id Related business.
	 * @param int|null    $user_id     Actor (defaults to the current user).
	 * @return void
	 */
	public static function record( string $action, ?string $entity_type = null, ?int $entity_id = null, array $meta = array(), ?int $business_id = null, ?int $user_id = null ): void {
		try {
			$request = Request::current();
			if ( null === $user_id ) {
				$current = Auth::user();
				$user_id = null !== $current ? (int) $current['id'] : null;
			}

			Database::instance()->insert(
				'audit_logs',
				array(
					'user_id'     => $user_id,
					'business_id' => $business_id,
					'action'      => mb_substr( $action, 0, 80 ),
					'entity_type' => null !== $entity_type ? mb_substr( $entity_type, 0, 40 ) : null,
					'entity_id'   => $entity_id,
					'ip'          => PHP_SAPI === 'cli' ? null : $request->ip_binary(),
					'user_agent'  => PHP_SAPI === 'cli' ? 'cli' : $request->user_agent(),
					'meta'        => empty( $meta ) ? null : json_encode( self::scrub( $meta ), JSON_UNESCAPED_UNICODE ),
				)
			);
		} catch ( Throwable $e ) {
			Logger::error( 'Audit log write failed', array( 'action' => $action, 'exception' => $e ) );
		}
	}

	/**
	 * Returns the label for an action.
	 *
	 * @param string $action Action.
	 * @return string
	 */
	public static function label( string $action ): string {
		return self::LABELS[ $action ] ?? $action;
	}

	/**
	 * Removes values whose keys look sensitive.
	 *
	 * @param array $meta Meta.
	 * @return array
	 */
	private static function scrub( array $meta ): array {
		foreach ( $meta as $key => $value ) {
			if ( preg_match( '/pass|secret|token|api_key|cipher/i', (string) $key ) ) {
				$meta[ $key ] = '[redacted]';
			} elseif ( is_array( $value ) ) {
				$meta[ $key ] = self::scrub( $value );
			}
		}
		return $meta;
	}
}
