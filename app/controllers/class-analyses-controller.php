<?php
/**
 * OptiVio — AI deep analyses (on demand, with confirmation and cost estimate).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Actions\Analysis_Service;
use OptiVio\Ai\Ai_Client;
use OptiVio\Ai\Prompts;
use OptiVio\Ai\Snapshot_Builder;
use OptiVio\Core\Database;
use OptiVio\Core\Http_Exception;
use OptiVio\Repositories\Businesses_Repository;
use OptiVio\Repositories\Settings_Repository;
use OptiVio\Security\Audit_Log;
use OptiVio\Security\Gate;
use Throwable;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Analyses controller.
 */
final class Analyses_Controller extends Controller {

	/**
	 * List of analyses of the active business.
	 *
	 * @return void
	 */
	public function index(): void {
		$business = $this->business( '/analyses' );
		$this->render(
			'analyses/index',
			array(
				'page_title'    => 'Analize AI',
				'page_subtitle' => (string) $business['name'] . ' · analiză profundă multi-sursă, doar la cerere',
				'business'      => $business,
				'rows'          => Database::instance()->get_results(
					'SELECT a.id, a.type, a.status, a.created_at, a.completed_at, u.display_name, r.model, r.cost_usd, r.prompt_tokens, r.completion_tokens,
						(SELECT COUNT(*) FROM action_cards c WHERE c.analysis_id = a.id) cards
					 FROM analyses a LEFT JOIN users u ON u.id = a.requested_by LEFT JOIN ai_requests r ON r.id = a.ai_request_id
					 WHERE a.business_id = ? ORDER BY a.id DESC LIMIT 50',
					array( (int) $business['id'] )
				),
				'can_manage'    => Gate::can_access_business( (int) $business['id'], 'manage' ),
				'month_spend'   => Ai_Client::month_spend(),
				'budget'        => (float) Settings_Repository::get( 'ai.monthly_budget_usd', 50 ),
			)
		);
	}

	/**
	 * Confirmation screen: model, snapshot size, estimated cost, budget.
	 *
	 * @return void
	 */
	public function create(): void {
		$business = $this->business( '/analyses', 'manage' );
		$snapshot = Snapshot_Builder::build( (int) $business['id'] );
		$chars    = mb_strlen( Prompts::deep_system() . json_encode( $snapshot, JSON_UNESCAPED_UNICODE ) );
		$this->render(
			'analyses/new',
			array(
				'page_title'    => 'Analiză profundă AI',
				'page_subtitle' => (string) $business['name'] . ' · confirmare',
				'business'      => $business,
				'preflight'     => Ai_Client::preflight( (int) $business['id'], 'reasoning', $chars ),
				'chars'         => $chars,
				'sections'      => array_keys( $snapshot ),
				'mock'          => Ai_Client::is_mock(),
			)
		);
	}

	/**
	 * Runs the analysis (explicit user POST).
	 *
	 * @return void
	 */
	public function store(): void {
		$business = $this->business( '/analyses', 'manage' );
		if ( (bool) Settings_Repository::get( 'ai.confirm_before_call', true ) && '1' !== (string) $this->request->input( 'confirm', '0' ) ) {
			$this->redirect_with( '/analyses/new', 'warning', 'Confirmați costul estimat înainte de rulare.' );
		}
		try {
			[ $id, $created, $invalid ] = Analysis_Service::deep( (int) $business['id'], (int) $this->user()['id'] );
		} catch ( Throwable $e ) {
			Audit_Log::record( 'ai.analysis_failed', 'business', (int) $business['id'], array( 'error' => mb_substr( $e->getMessage(), 0, 200 ) ), (int) $business['id'] );
			$this->redirect_with( '/analyses', 'error', $e->getMessage() );
		}
		Audit_Log::record( 'ai.analysis', 'analysis', $id, array( 'cards' => $created, 'invalid' => $invalid ), (int) $business['id'] );
		$this->redirect_with( '/analyses/' . $id, 'success', sprintf( 'Analiza este gata: %d Action Cards noi%s.', $created, $invalid > 0 ? sprintf( ' (%d propuneri invalide au fost eliminate la validare)', $invalid ) : '' ) );
	}

	/**
	 * Analysis detail.
	 *
	 * @return void
	 * @throws Http_Exception 404.
	 */
	public function show(): void {
		$business = $this->business( '/analyses' );
		$row      = Database::instance()->get_row(
			'SELECT a.*, r.model, r.cost_usd, r.prompt_tokens, r.completion_tokens, r.duration_ms, u.display_name
			 FROM analyses a LEFT JOIN ai_requests r ON r.id = a.ai_request_id LEFT JOIN users u ON u.id = a.requested_by WHERE a.id = ? AND a.business_id = ?',
			array( $this->request->param_int( 'id' ), (int) $business['id'] )
		);
		if ( null === $row ) {
			throw Http_Exception::not_found();
		}
		$this->render(
			'analyses/show',
			array(
				'page_title'    => 'Analiza #' . (int) $row['id'],
				'page_subtitle' => (string) $business['name'] . ' · ' . ov_datetime( $row['created_at'] ),
				'analysis'      => $row,
				'cards'         => Database::instance()->get_results( 'SELECT id, category, title, status, priority, impact_score FROM action_cards WHERE analysis_id = ? AND business_id = ? ORDER BY priority, id', array( (int) $row['id'], (int) $business['id'] ) ),
			)
		);
	}

	/**
	 * Active business (authorized) or redirect to the chooser.
	 *
	 * @param string $path       Path.
	 * @param string $permission Permission.
	 * @return array
	 */
	private function business( string $path, string $permission = 'view' ): array {
		$id = Gate::active_business_id();
		if ( 0 === $id ) {
			$this->redirect_with( '/keywords', 'warning', 'Selectați mai întâi afacerea activă.' );
		}
		Gate::authorize_business( $id, $permission );
		unset( $path );
		return (array) Businesses_Repository::find( $id );
	}
}
