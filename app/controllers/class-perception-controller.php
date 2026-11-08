<?php
/**
 * OptiVio — Business tab "Percepție": history of perceptions, gaps vs reality, internal perception per site.
 *
 * The internal perception reads the content mirrored by the OptiVio Connector plugin, so it is
 * available per site only after the plugin is installed, connected and synchronized.
 * AI runs only on the explicit POST of a user with "manage" rights (RULES.md R5).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Ai\Ai_Client;
use OptiVio\Core\Database;
use OptiVio\Onboarding\Drafts;
use OptiVio\Onboarding\Perception;
use OptiVio\Onboarding\Perception_Gaps;
use OptiVio\Repositories\Settings_Repository;
use OptiVio\Security\Audit_Log;
use OptiVio\Security\Gate;
use RuntimeException;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Perception tab.
 */
final class Perception_Controller extends Business_Scoped_Controller {

	/**
	 * Tab page.
	 *
	 * @return void
	 */
	public function index(): void {
		$business = $this->business( 'view' );
		$bid      = (int) $business['id'];
		$list     = Perception::for_business( $bid );
		$wanted   = absint( $this->request->query( 'p', 0 ) );
		$current  = null;
		foreach ( $list as $row ) {
			if ( ( 0 === $wanted && null === $current ) || (int) $row['id'] === $wanted ) {
				$current = Perception::find( (int) $row['id'] );
			}
		}
		$baseline = Perception::baseline( $bid );

		$sites = array();
		foreach ( $this->sites( $bid ) as $site ) {
			$site['ready'] = 'connected' === $site['connector_status'] && (int) $site['content_count'] > 0;
			if ( $site['ready'] ) {
				$input             = Perception::build_input( Perception::pages_from_site( (int) $site['id'], Drafts::limits()['site'] ), Drafts::limits()['chars'] );
				$site['preflight'] = Ai_Client::preflight( $bid, 'perception', mb_strlen( Perception::user_prompt( $input ) ) + 3000 );
				$site['pages']     = count( $input['pages'] );
			}
			$sites[] = $site;
		}

		$this->render_tab(
			'businesses/perception',
			$business,
			'perception',
			array(
				'perceptions' => $list,
				'current'     => $current,
				'baseline'    => $baseline,
				'gaps'        => null !== $current ? Perception_Gaps::for_perception( (int) $current['id'] ) : array(),
				'base_gaps'   => null !== $baseline && null !== $current && (int) $baseline['id'] !== (int) $current['id'] ? Perception_Gaps::for_perception( (int) $baseline['id'] ) : array(),
				'sites'       => $sites,
				'confirm'     => (bool) Settings_Repository::get( 'ai.confirm_before_call', true ),
				'is_admin'    => Gate::is_super_admin( $this->user() ),
			)
		);
	}

	/**
	 * Internal perception of one site (AI, on demand).
	 *
	 * @return void
	 */
	public function internal(): void {
		$business = $this->business( 'manage' );
		$bid      = (int) $business['id'];
		$site_id  = $this->request->param_int( 'site_id' );
		$back     = $this->path( $business, '/perception' );
		$site     = null;
		foreach ( $this->sites( $bid ) as $s ) {
			if ( (int) $s['id'] === $site_id ) {
				$site = $s;
			}
		}
		if ( null === $site ) {
			$this->redirect_with( $back, 'error', 'Site inexistent.' );
		}
		if ( 'connected' !== $site['connector_status'] || (int) $site['content_count'] <= 0 ) {
			$this->redirect_with( $back, 'error', 'Instalați și conectați pluginul OptiVio Connector pe acest site, apoi așteptați sincronizarea conținutului.' );
		}
		if ( (bool) Settings_Repository::get( 'ai.confirm_before_call', true ) && '1' !== (string) $this->request->input( 'confirm_cost', '0' ) ) {
			$this->redirect_with( $back, 'error', 'Confirmați costul estimat înainte de a porni analiza AI.' );
		}
		$input = Perception::build_input( Perception::pages_from_site( $site_id, Drafts::limits()['site'] ), Drafts::limits()['chars'] );
		try {
			$pid = Perception::run( (int) $this->user()['id'], $bid, 'internal', $input, array( 'site_id' => $site_id ) );
		} catch ( RuntimeException $e ) {
			$this->redirect_with( $back, 'error', $e->getMessage() );
		}
		$result = Perception_Gaps::compute( $bid, $pid );
		$cards  = Perception_Gaps::cards( $bid, $pid );
		Audit_Log::record(
			'perception.internal',
			'business_perception',
			$pid,
			array(
				'site_id' => $site_id,
				'pages'   => count( $input['pages'] ),
				'clarity' => $result['score'],
				'cards'   => $cards,
			),
			$bid
		);
		$this->redirect_with( $back . '?p=' . $pid, 'success', sprintf( 'Percepția din interior este gata: scor de claritate %d%%, %d carduri noi.', $result['score'], $cards ) );
	}

	/**
	 * Sites with connector status and mirrored content.
	 *
	 * @param int $business_id Business.
	 * @return array
	 */
	private function sites( int $business_id ): array {
		return Database::instance()->get_results(
			"SELECT s.id, s.name, s.url, s.cms, s.connector_status, s.content_synced_at,
				(SELECT COUNT(*) FROM wp_content c WHERE c.site_id = s.id AND c.status = 'publish' AND c.object_type = 'post') AS content_count
			 FROM sites s WHERE s.business_id = ? AND s.status <> 'archived' ORDER BY s.id",
			array( $business_id )
		);
	}
}
