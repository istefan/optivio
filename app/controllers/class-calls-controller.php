<?php
/**
 * OptiVio — Calls & revenue: manual entry, CSV import, deterministic analytics.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Core\Http_Exception;
use OptiVio\Core\Validator;
use OptiVio\Repositories\Businesses_Repository;
use OptiVio\Repositories\Calls_Repository;
use OptiVio\Repositories\Settings_Repository;
use OptiVio\Security\Audit_Log;
use OptiVio\Security\Gate;
use OptiVio\Services\Call_Csv_Importer;
use OptiVio\Services\Call_Matcher;
use OptiVio\Services\Calls_Analytics;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Calls controller.
 */
final class Calls_Controller extends Controller {

	/**
	 * Allowed periods.
	 */
	private const PERIODS = array( 7, 30, 90, 180, 365 );

	/**
	 * Calls dashboard + list + manual entry.
	 *
	 * @return void
	 */
	public function index(): void {
		$days    = absint( $this->request->query( 'days', 30 ) );
		$days    = in_array( $days, self::PERIODS, true ) ? $days : 30;
		$outcome = sanitize_key( $this->request->query( 'outcome', '' ) );
		$outcome = 'rejected' === $outcome || isset( Calls_Repository::OUTCOMES[ $outcome ] ) ? $outcome : '';
		$page    = max( 1, absint( $this->request->query( 'page', 1 ) ) );
		$scope   = Gate::scope_business_ids();
		$stats   = new Calls_Analytics( $scope, $days );
		$list    = Calls_Repository::paginate(
			$scope,
			array(
				'days'    => $days,
				'outcome' => $outcome,
			),
			$page
		);

		$this->render(
			'calls/index',
			array(
				'page_title'    => 'Apeluri & venituri',
				'page_subtitle' => 'Apeluri acceptate, refuzate și pierdute, corelate cu click-urile tel: și cuvintele cheie',
				'days'          => $days,
				'outcome'       => $outcome,
				'totals'        => $stats->totals(),
				'by_keyword'    => $stats->by_keyword(),
				'by_channel'    => $stats->by_channel(),
				'by_outcome'    => $stats->by_outcome(),
				'by_hour'       => $stats->by_hour(),
				'schedule'      => $stats->schedule_suggestions(),
				'rows'          => $list['rows'],
				'total'         => $list['total'],
				'page'          => $page,
				'pages'         => max( 1, (int) ceil( $list['total'] / 25 ) ),
				'manageable'    => $this->manageable_businesses(),
				'errors'        => $this->pull_errors(),
				'currency'      => (string) Settings_Repository::get( 'general.currency', 'RON' ),
			)
		);
	}

	/**
	 * Stores a manual call.
	 *
	 * @return void
	 */
	public function store(): void {
		$business_id = absint( $this->request->input( 'business_id', 0 ) );
		Gate::authorize_business( $business_id, 'manage' );

		[ $data, $v ] = $this->collect( $business_id );
		if ( $v->fails() ) {
			$this->back_with_errors( '/calls', $v->errors(), array_merge( $data, array( 'business_id' => $business_id ) ) );
		}

		$id      = Calls_Repository::create( $business_id, $data, $this->text( 'caller', 40 ), 'manual', (int) $this->user()['id'] );
		$matched = Call_Matcher::match_call( $id );
		Audit_Log::record( 'call.create', 'call', $id, array( 'outcome' => $data['outcome'] ), $business_id );
		$this->redirect_with( '/calls', 'success', 'Apelul a fost înregistrat' . ( $matched ? ' și corelat automat cu un click tel:.' : '.' ) );
	}

	/**
	 * Edit form.
	 *
	 * @return void
	 */
	public function edit(): void {
		$call = $this->authorized_call( 'manage' );
		$this->render(
			'calls/edit',
			array(
				'page_title'    => 'Editare apel',
				'page_subtitle' => ov_datetime( $call['call_time'] ),
				'call'          => $call,
				'business'      => Businesses_Repository::find( (int) $call['business_id'] ),
				'errors'        => $this->pull_errors(),
			)
		);
	}

	/**
	 * Updates a call.
	 *
	 * @return void
	 */
	public function update(): void {
		$call = $this->authorized_call( 'manage' );
		[ $data, $v ] = $this->collect( (int) $call['business_id'] );
		if ( $v->fails() ) {
			$this->back_with_errors( '/calls/' . (int) $call['id'] . '/edit', $v->errors(), $data );
		}
		Calls_Repository::update( (int) $call['id'], (int) $call['business_id'], $data );
		Audit_Log::record( 'call.update', 'call', (int) $call['id'], array( 'outcome' => $data['outcome'] ), (int) $call['business_id'] );
		$this->redirect_with( '/calls', 'success', 'Apelul a fost actualizat.' );
	}

	/**
	 * Deletes a call.
	 *
	 * @return void
	 */
	public function destroy(): void {
		$call = $this->authorized_call( 'manage' );
		Calls_Repository::delete( (int) $call['id'], (int) $call['business_id'] );
		Audit_Log::record( 'call.delete', 'call', (int) $call['id'], array(), (int) $call['business_id'] );
		$this->redirect_with( '/calls', 'success', 'Apelul a fost șters.' );
	}

	/**
	 * Import form.
	 *
	 * @return void
	 */
	public function import_form(): void {
		$this->render(
			'calls/import',
			array(
				'page_title'    => 'Import apeluri (CSV)',
				'page_subtitle' => 'Exporturi din centrala telefonică sau din foi de calcul',
				'manageable'    => $this->manageable_businesses(),
				'result'        => \OptiVio\Core\Session::get( '_import_result' ),
			)
		);
		\OptiVio\Core\Session::remove( '_import_result' );
	}

	/**
	 * Handles the CSV upload.
	 *
	 * @return void
	 */
	public function import(): void {
		$business_id = absint( $this->request->input( 'business_id', 0 ) );
		Gate::authorize_business( $business_id, 'manage' );

		$file = $this->request->file( 'csv' );
		if ( null === $file || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			$this->redirect_with( '/calls/import', 'error', 'Selectați un fișier CSV.' );
		}
		if ( (int) $file['size'] > Call_Csv_Importer::MAX_BYTES ) {
			$this->redirect_with( '/calls/import', 'error', 'Fișierul depășește 2 MB.' );
		}
		$mime = ( new \finfo( FILEINFO_MIME_TYPE ) )->file( (string) $file['tmp_name'] );
		$name = (string) $file['name'];
		if ( ! in_array( $mime, array( 'text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel' ), true ) || ! preg_match( '/\.(csv|txt)$/i', $name ) ) {
			$this->redirect_with( '/calls/import', 'error', 'Sunt acceptate doar fișiere .csv (text).' );
		}

		$result = Call_Csv_Importer::import( $business_id, (string) $file['tmp_name'], $name, (int) $this->user()['id'] );
		Audit_Log::record( 'call.import', 'call_import_batch', $result['batch_id'] ?: null, array_diff_key( $result, array( 'errors' => 1 ) ), $business_id );
		\OptiVio\Core\Session::set( '_import_result', $result );
		$this->redirect_with( '/calls/import', $result['imported'] > 0 ? 'success' : 'error', sprintf( 'Import: %d rânduri importate, %d omise, %d corelate cu click-uri tel:.', $result['imported'], $result['skipped'], $result['matched'] ) );
	}

	/**
	 * Loads a call and checks access to its business.
	 *
	 * @param string $permission view|manage.
	 * @return array
	 * @throws Http_Exception 404.
	 */
	private function authorized_call( string $permission ): array {
		$call = Calls_Repository::find( $this->request->param_int( 'id' ) );
		if ( null === $call ) {
			throw Http_Exception::not_found();
		}
		Gate::authorize_business( (int) $call['business_id'], $permission );
		return $call;
	}

	/**
	 * Businesses where the user may add calls.
	 *
	 * @return array<int,string>
	 */
	private function manageable_businesses(): array {
		$result = array();
		foreach ( Businesses_Repository::options( Gate::accessible_business_ids() ) as $id => $name ) {
			if ( Gate::can_access_business( (int) $id, 'manage' ) ) {
				$result[ (int) $id ] = $name;
			}
		}
		return $result;
	}

	/**
	 * Collects and validates call fields.
	 *
	 * @param int $business_id Business id.
	 * @return array{0:array,1:Validator}
	 */
	private function collect( int $business_id ): array {
		unset( $business_id );
		$time_raw = str_replace( 'T', ' ', $this->text( 'call_time', 20 ) );
		$time     = Call_Csv_Importer::parse_time( $time_raw, ov_timezone() );
		$revenue  = Call_Csv_Importer::parse_money( $this->text( 'revenue_amount', 20 ) );
		$outcome  = sanitize_key( $this->request->input( 'outcome', '' ) );
		$channel  = sanitize_key( $this->request->input( 'channel', 'other' ) );
		$duration = $this->text( 'duration_sec', 6 );

		$data = array(
			'call_time'      => $time,
			'duration_sec'   => '' !== $duration && ctype_digit( $duration ) ? (int) $duration : null,
			'outcome'        => $outcome,
			'outcome_note'   => $this->text( 'outcome_note', 500 ) ?: null,
			'revenue_amount' => 'accepted' === $outcome ? $revenue : null,
			'keyword_text'   => $this->text( 'keyword_text', 190 ) ?: null,
			'channel'        => $channel,
		);

		$v = new Validator();
		$v->in( 'outcome', $outcome, array_keys( Calls_Repository::OUTCOMES ) )
			->in( 'channel', $channel, array_keys( \OptiVio\Services\Attribution::CHANNELS ) );
		if ( null === $time ) {
			$v->add( 'call_time', 'Data și ora sunt obligatorii (nu în viitor).' );
		}
		return array( $data, $v );
	}
}
