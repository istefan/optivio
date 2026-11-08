<?php
/**
 * OptiVio — Per-business AI configuration (OpenRouter key + model overrides) and RepuVio link.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Core\Validator;
use OptiVio\Repositories\Ai_Profiles_Repository;
use OptiVio\Repositories\Credentials_Repository;
use OptiVio\Security\Audit_Log;
use OptiVio\Services\Ai_Config;
use OptiVio\Services\Repuvio_Connector;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Business AI & connectors controller.
 */
final class Business_Ai_Controller extends Business_Scoped_Controller {

	/**
	 * AI & connectors tab.
	 *
	 * @return void
	 */
	public function index(): void {
		$business = $this->business( 'view' );
		$id       = (int) $business['id'];
		$this->render_tab(
			'businesses/ai',
			$business,
			'ai',
			array(
				'overrides'   => Ai_Profiles_Repository::for_business( $id ),
				'effective'   => Ai_Config::effective( $id ),
				'client_key'  => Credentials_Repository::find_for_business( $id, 'openrouter' ),
				'ai_profiles' => Settings_Controller::AI_PROFILES,
			)
		);
	}

	/**
	 * Saves model overrides (empty = inherit the global profile).
	 *
	 * @return void
	 */
	public function update(): void {
		$business = $this->business( 'manage' );
		$back     = $this->path( $business, '/ai' );
		$v        = new Validator();
		$values   = array();

		foreach ( Ai_Profiles_Repository::PROFILES as $profile ) {
			$model  = strtolower( $this->text( 'model_' . $profile, 120 ) );
			$temp   = $this->text( 'temperature_' . $profile, 5 );
			$tokens = $this->text( 'max_tokens_' . $profile, 6 );

			$v->pattern( 'model_' . $profile, $model, '#^[a-z0-9._\-]+/[a-z0-9._:\-]+$#', 'Format model OpenRouter invalid.' );
			$temperature = '' === $temp ? null : (float) str_replace( ',', '.', $temp );
			$max_tokens  = '' === $tokens ? null : absint( $tokens );
			if ( null !== $temperature && ( $temperature < 0 || $temperature > 2 ) ) {
				$v->add( 'temperature_' . $profile, 'Între 0 și 2.' );
			}
			if ( null !== $max_tokens && ( $max_tokens < 256 || $max_tokens > 64000 ) ) {
				$v->add( 'max_tokens_' . $profile, 'Între 256 și 64000.' );
			}
			$values[ $profile ] = array( $model, $temperature, $max_tokens );
		}

		if ( $v->fails() ) {
			$this->back_with_errors( $back, $v->errors(), array() );
		}

		foreach ( $values as $profile => $value ) {
			Ai_Profiles_Repository::save( (int) $business['id'], $profile, $value[0], $value[1], $value[2], (int) $this->user()['id'] );
		}
		Audit_Log::record( 'business.ai_profiles', 'business', (int) $business['id'], array(), (int) $business['id'] );
		$this->redirect_with( $back, 'success', 'Profilurile AI ale clientului au fost salvate.' );
	}

	/**
	 * Saves or deletes the client's own OpenRouter key.
	 *
	 * @return void
	 */
	public function save_key(): void {
		$business = $this->business( 'manage' );
		$id       = (int) $business['id'];
		$back     = $this->path( $business, '/ai' );

		if ( 'delete' === sanitize_key( $this->request->input( 'action', '' ) ) ) {
			Credentials_Repository::delete_for_business( $id, 'openrouter' );
			Audit_Log::record( 'credential.delete', 'api_credential', null, array( 'provider' => 'openrouter', 'scope' => 'business' ), $id );
			$this->redirect_with( $back, 'success', 'Cheia clientului a fost ștearsă. Se va folosi cheia globală.' );
		}

		$secret = trim( (string) $this->request->input( 'secret', '' ) );
		if ( strlen( $secret ) < 16 || strlen( $secret ) > 512 || ! preg_match( '/^[\x21-\x7E]+$/', $secret ) ) {
			$this->redirect_with( $back, 'error', 'Cheia API pare invalidă (16–512 caractere, fără spații).' );
		}

		$cred_id = Credentials_Repository::save_for_business( $id, 'openrouter', $secret, (int) $this->user()['id'] );
		Audit_Log::record( 'credential.save', 'api_credential', $cred_id, array( 'provider' => 'openrouter', 'scope' => 'business' ), $id );
		$this->redirect_with( $back, 'success', 'Cheia OpenRouter a clientului a fost salvată criptat.' );
	}

	/**
	 * Links / unlinks a RepuVio account (reserved integration).
	 *
	 * @return void
	 */
	public function save_repuvio(): void {
		$business = $this->business( 'manage' );
		$account  = $this->text( 'repuvio_account_id', 64 );
		$back     = $this->path( $business, '/ai' );

		$v = ( new Validator() )->pattern( 'repuvio_account_id', $account, '/^[A-Za-z0-9_\-]{3,64}$/', 'ID cont invalid (litere, cifre, - și _).' );
		if ( $v->fails() ) {
			$this->back_with_errors( $back, $v->errors(), array( 'repuvio_account_id' => $account ) );
		}

		$status = Repuvio_Connector::link( (int) $business['id'], $account );
		Audit_Log::record( 'business.repuvio_link', 'business', (int) $business['id'], array( 'status' => $status ), (int) $business['id'] );
		$this->redirect_with( $back, 'success', '' === $account ? 'Legătura RepuVio a fost eliminată.' : 'ID-ul RepuVio a fost salvat (integrarea se activează într-o fază ulterioară).' );
	}
}
