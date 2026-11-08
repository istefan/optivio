<?php
/**
 * OptiVio — Onboarding wizard (Super Admin only).
 *
 * 1. Sites and pages (deterministic discovery) → fetch in batches (job + AJAX, zero AI);
 * 2. "What the AI understands" — ONE on-demand perception call (profile `perception`);
 * 3. Confirm the profile — the business is created here, gaps perception vs reality computed;
 * 4. USP, rules, commercial data; 5. Plugin per site; 6. Google; 7. Summary.
 * A draft linked to an existing business (re-analysis) skips the confirmation and goes
 * straight to the gaps.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Ai\Ai_Client;
use OptiVio\Core\Database;
use OptiVio\Core\Http_Exception;
use OptiVio\Core\Response;
use OptiVio\Core\Validator;
use OptiVio\Engine\Engine_Runner;
use OptiVio\Google\Google_Matcher;
use OptiVio\Onboarding\Drafts;
use OptiVio\Onboarding\Page_Discovery;
use OptiVio\Onboarding\Perception;
use OptiVio\Onboarding\Perception_Gaps;
use OptiVio\Repositories\Business_Children_Repository;
use OptiVio\Repositories\Businesses_Repository;
use OptiVio\Repositories\Settings_Repository;
use OptiVio\Repositories\Sites_Repository;
use OptiVio\Security\Audit_Log;
use OptiVio\Services\Content_Ingestor;
use OptiVio\Services\Readiness_Score;
use OptiVio\Services\Usp_Builder;
use RuntimeException;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Onboarding wizard.
 */
final class Onboarding_Controller extends Controller {

	/**
	 * Wizard steps: key => [number, label].
	 */
	public const STEPS = array(
		'pages'      => array( 1, 'Site-uri și pagini' ),
		'analyze'    => array( 2, 'Ce înțelege AI-ul' ),
		'confirm'    => array( 3, 'Confirmă profilul' ),
		'commercial' => array( 4, 'USP și reguli' ),
		'plugin'     => array( 5, 'Plugin' ),
		'google'     => array( 6, 'Google' ),
		'summary'    => array( 7, 'Rezumat' ),
	);

	/**
	 * Drafts list + start form.
	 *
	 * @return void
	 */
	public function index(): void {
		$this->render(
			'onboarding/index',
			array(
				'page_title'    => 'Afacere nouă (wizard)',
				'page_subtitle' => 'Pornim de la ce înțelege un bot din site-urile clientului',
				'drafts'        => Drafts::open_list(),
				'limits'        => Drafts::limits(),
				'errors'        => $this->pull_errors(),
			)
		);
	}

	/**
	 * Creates a draft from the entered sites and discovers their pages.
	 *
	 * @return void
	 */
	public function start(): void {
		$raw   = preg_split( '/[\s,]+/', (string) $this->textarea( 'sites', 2000 ) );
		$roots = array();
		$bad   = array();
		foreach ( array_filter( (array) $raw ) as $item ) {
			$root = Page_Discovery::site_root( (string) $item );
			if ( null === $root ) {
				$bad[] = $item;
			} else {
				$roots[ Page_Discovery::host_key( $root ) ] = $root;
			}
		}
		if ( empty( $roots ) || ! empty( $bad ) ) {
			$this->back_with_errors( '/onboarding', array( 'sites' => empty( $roots ) ? 'Introduceți cel puțin un domeniu valid (ex. exemplu.ro).' : 'Domenii invalide: ' . implode( ', ', array_slice( $bad, 0, 5 ) ) ), array( 'sites' => $this->textarea( 'sites', 2000 ) ) );
		}
		$roots = array_slice( array_values( $roots ), 0, 5 );
		$id    = Drafts::create( (int) $this->user()['id'], (string) Page_Discovery::host_key( $roots[0] ), array( 'mode' => 'new' ) );
		$this->discover_into( $id, $roots );
		Audit_Log::record( 'onboarding.start', 'onboarding_draft', $id, array( 'sites' => array_map( array( Page_Discovery::class, 'host_key' ), $roots ) ) );
		Response::redirect( '/onboarding/' . $id . '/pages' );
	}

	/**
	 * Resumes a draft at its current step.
	 *
	 * @return void
	 */
	public function resume(): void {
		$draft = $this->draft();
		Response::redirect( '/onboarding/' . (int) $draft['id'] . '/' . $this->current_step( $draft ) );
	}

	/**
	 * Step 1: page selection.
	 *
	 * @return void
	 */
	public function pages(): void {
		$draft = $this->draft();
		$this->guard_pre_business( $draft );
		$this->render(
			'onboarding/pages',
			$this->base( $draft, 'pages' ) + array(
				'pages'  => Drafts::pages( (int) $draft['id'] ),
				'limits' => Drafts::limits(),
				'errors' => $this->pull_errors(),
			)
		);
	}

	/**
	 * Saves the selection (+ manual URLs, + new sites) and starts fetching.
	 *
	 * @return void
	 */
	public function save_pages(): void {
		$draft = $this->draft();
		$this->guard_pre_business( $draft );
		$id     = (int) $draft['id'];
		$db     = Database::instance();
		$limits = Drafts::limits();

		$new_sites = array();
		foreach ( array_filter( (array) preg_split( '/[\s,]+/', $this->textarea( 'new_sites', 1000 ) ) ) as $item ) {
			$root = Page_Discovery::site_root( (string) $item );
			if ( null !== $root ) {
				$new_sites[ Page_Discovery::host_key( $root ) ] = $root;
			}
		}
		$known = array_column( (array) ( $draft['state']['sites'] ?? array() ), 'root', 'host' );
		$new_sites = array_diff_key( $new_sites, $known );
		if ( ! empty( $new_sites ) ) {
			$this->discover_into( $id, array_values( $new_sites ) );
			$this->redirect_with( '/onboarding/' . $id . '/pages', 'success', 'Am adăugat site-urile noi. Verificați selecția paginilor.' );
		}

		$selected = array_map( 'absint', (array) $this->request->input( 'page', array() ) );
		$db->query( 'UPDATE onboarding_pages SET selected = 0 WHERE draft_id = ?', array( $id ) );
		if ( ! empty( $selected ) ) {
			$db->query( 'UPDATE onboarding_pages SET selected = 1 WHERE draft_id = ? AND id IN (' . Database::in_placeholders( $selected ) . ')', array_merge( array( $id ), $selected ) );
		}

		// Manual URLs: must belong to one of the draft sites.
		$hosts  = array_column( (array) ( $draft['state']['sites'] ?? array() ), 'root', 'host' );
		$manual = array();
		$bad    = array();
		foreach ( array_filter( array_map( 'trim', explode( "\n", $this->textarea( 'manual_urls', 20000 ) ) ) ) as $url ) {
			$url  = sanitize_text_field( $url );
			$host = Page_Discovery::host_key( $url );
			$root = $hosts[ $host ] ?? null;
			$ok   = null !== $root ? Page_Discovery::clean_url( $url, $root ) : null;
			if ( null === $ok ) {
				$bad[] = $url;
				continue;
			}
			$manual[ $root ][] = array(
				'url'      => $ok,
				'title'    => '',
				'source'   => 'manual',
				'type'     => Page_Discovery::classify( $ok, '', $root ),
				'selected' => true,
			);
		}
		foreach ( $manual as $root => $pages ) {
			Drafts::add_pages( $id, $root, $pages );
			foreach ( $pages as $p ) {
				$db->query( 'UPDATE onboarding_pages SET selected = 1 WHERE draft_id = ? AND url_hash = ?', array( $id, hash( 'sha256', Content_Ingestor::normalize_url( $p['url'] ) ) ) );
			}
		}

		$override = '1' === (string) $this->request->input( 'robots_override', '0' ) ? 1 : 0;
		$db->update( 'onboarding_drafts', array( 'robots_override' => $override ), array( 'id' => $id ) );

		// Limits: per site and per run.
		$errors = array();
		foreach ( $db->get_results( 'SELECT site_url, COUNT(*) AS n FROM onboarding_pages WHERE draft_id = ? AND selected = 1 GROUP BY site_url', array( $id ) ) as $row ) {
			if ( (int) $row['n'] > $limits['site'] ) {
				$errors['page'] = sprintf( 'Maximum %d pagini pe site (%s are %d).', $limits['site'], $row['site_url'], (int) $row['n'] );
			}
		}
		$total = (int) $db->get_var( 'SELECT COUNT(*) FROM onboarding_pages WHERE draft_id = ? AND selected = 1', array( $id ) );
		if ( $total > $limits['run'] ) {
			$errors['page'] = sprintf( 'Maximum %d pagini pe analiză (ați ales %d).', $limits['run'], $total );
		} elseif ( 0 === $total ) {
			$errors['page'] = 'Alegeți cel puțin o pagină.';
		}
		if ( ! empty( $bad ) ) {
			$errors['manual_urls'] = 'URL-uri ignorate (alt domeniu sau tip nepermis): ' . implode( ', ', array_slice( $bad, 0, 5 ) );
		}
		if ( ! empty( $errors ) ) {
			$this->back_with_errors( '/onboarding/' . $id . '/pages', $errors, array() );
		}

		Drafts::start_fetch( $id, (int) $this->user()['id'] );
		Response::redirect( '/onboarding/' . $id . '/fetch' );
	}

	/**
	 * Step 1b: fetch progress page.
	 *
	 * @return void
	 */
	public function fetch_view(): void {
		$draft = $this->draft();
		$this->guard_pre_business( $draft );
		$this->render(
			'onboarding/fetch',
			$this->base( $draft, 'pages' ) + array(
				'pages'    => Drafts::pages( (int) $draft['id'], true ),
				'progress' => Drafts::progress( (int) $draft['id'] ),
			)
		);
	}

	/**
	 * AJAX: fetches the next batch and returns the progress (deterministic, no AI).
	 *
	 * @return void
	 */
	public function fetch_step(): void {
		$draft = $this->draft();
		@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- May be disabled on shared hosting.
		$progress = 'fetching' === $draft['status'] ? Drafts::process( (int) $draft['id'] ) : Drafts::progress( (int) $draft['id'] );
		$rows     = array();
		foreach ( Drafts::pages( (int) $draft['id'], true ) as $p ) {
			$rows[] = array(
				'id'     => (int) $p['id'],
				'status' => (string) $p['fetch_status'],
				'error'  => (string) $p['error'],
				'chars'  => mb_strlen( (string) $p['body_text'] ),
			);
		}
		Response::json(
			array(
				'progress' => $progress,
				'pages'    => $rows,
				'done'     => 0 === $progress['pending'],
			)
		);
	}

	/**
	 * Retries one page.
	 *
	 * @return void
	 */
	public function page_retry(): void {
		$draft = $this->draft();
		$this->guard_pre_business( $draft );
		Database::instance()->query( "UPDATE onboarding_pages SET fetch_status = 'pending', error = NULL WHERE id = ? AND draft_id = ?", array( $this->request->param_int( 'page_id' ), (int) $draft['id'] ) );
		Database::instance()->update( 'onboarding_drafts', array( 'status' => 'fetching' ), array( 'id' => (int) $draft['id'] ) );
		Response::redirect( '/onboarding/' . (int) $draft['id'] . '/fetch' );
	}

	/**
	 * Excludes one page from the analysis.
	 *
	 * @return void
	 */
	public function page_exclude(): void {
		$draft = $this->draft();
		$this->guard_pre_business( $draft );
		Database::instance()->query( 'UPDATE onboarding_pages SET selected = 0 WHERE id = ? AND draft_id = ?', array( $this->request->param_int( 'page_id' ), (int) $draft['id'] ) );
		Response::redirect( '/onboarding/' . (int) $draft['id'] . '/fetch' );
	}

	/**
	 * Step 2: preflight + perception result.
	 *
	 * @return void
	 */
	public function analyze_view(): void {
		$draft = $this->draft();
		$this->guard_pre_business( $draft );
		$progress = Drafts::progress( (int) $draft['id'] );
		if ( $progress['pending'] > 0 ) {
			Response::redirect( '/onboarding/' . (int) $draft['id'] . '/fetch' );
		}
		$input      = Perception::build_input( Perception::pages_from_draft( (int) $draft['id'] ), Drafts::limits()['chars'] );
		$perception = ! empty( $draft['state']['perception_id'] ) ? Perception::find( (int) $draft['state']['perception_id'] ) : null;
		$business   = (int) ( $draft['business_id'] ?? 0 );
		$this->render(
			'onboarding/analyze',
			$this->base( $draft, 'analyze' ) + array(
				'input'      => $input,
				'progress'   => $progress,
				'preflight'  => Ai_Client::preflight( $business, 'perception', mb_strlen( Perception::user_prompt( $input ) ) + 3000 ),
				'confirm'    => (bool) Settings_Repository::get( 'ai.confirm_before_call', true ),
				'perception' => $perception,
			)
		);
	}

	/**
	 * Step 2: runs the AI perception (explicit user POST only).
	 *
	 * @return void
	 */
	public function analyze(): void {
		$draft = $this->draft();
		$this->guard_pre_business( $draft );
		$id   = (int) $draft['id'];
		$back = '/onboarding/' . $id . '/analyze';
		if ( (bool) Settings_Repository::get( 'ai.confirm_before_call', true ) && '1' !== (string) $this->request->input( 'confirm_cost', '0' ) ) {
			$this->redirect_with( $back, 'error', 'Confirmați costul estimat înainte de a porni analiza AI.' );
		}
		$business_id = (int) ( $draft['business_id'] ?? 0 );
		$input       = Perception::build_input( Perception::pages_from_draft( $id ), Drafts::limits()['chars'] );
		try {
			$pid = Perception::run( (int) $this->user()['id'], $business_id, 'external', $input, array( 'draft_id' => $id ) );
		} catch ( RuntimeException $e ) {
			$this->redirect_with( $back, 'error', $e->getMessage() );
		}
		Drafts::patch( $id, array( 'perception_id' => $pid ), array( 'status' => 'analyzed' ) );
		Audit_Log::record( 'onboarding.perception', 'business_perception', $pid, array( 'pages' => count( $input['pages'] ), 'chars' => $input['chars'] ), $business_id > 0 ? $business_id : null );

		if ( $business_id > 0 ) {
			// Re-analysis of an existing business: compare with the stored decisions right away.
			$result = Perception_Gaps::compute( $business_id, $pid );
			$cards  = Perception_Gaps::cards( $business_id, $pid );
			Database::instance()->update( 'onboarding_drafts', array( 'status' => 'converted' ), array( 'id' => $id ) );
			$this->redirect_with( '/businesses/' . $business_id . '/perception?p=' . $pid, 'success', sprintf( 'Percepție nouă: scor de claritate %d%%, %d carduri noi.', $result['score'], $cards ) );
		}
		$this->redirect_with( '/onboarding/' . $id . '/confirm', 'success', 'Analiza AI este gata. Confirmați sau corectați ce a înțeles.' );
	}

	/**
	 * Step 3: confirmation form.
	 *
	 * @return void
	 */
	public function confirm_view(): void {
		$draft = $this->draft();
		$this->guard_pre_business( $draft );
		$perception = ! empty( $draft['state']['perception_id'] ) ? Perception::find( (int) $draft['state']['perception_id'] ) : null;
		if ( null === $perception ) {
			Response::redirect( '/onboarding/' . (int) $draft['id'] . '/analyze' );
		}
		$this->render(
			'onboarding/confirm',
			$this->base( $draft, 'confirm' ) + array(
				'perception' => $perception,
				'pillars'    => Perception::pillar_keys(),
				'errors'     => $this->pull_errors(),
			)
		);
	}

	/**
	 * Step 3: creates the business, its sites and services, then computes the gaps.
	 *
	 * @return void
	 */
	public function confirm(): void {
		$draft = $this->draft();
		$this->guard_pre_business( $draft );
		$id         = (int) $draft['id'];
		$perception = ! empty( $draft['state']['perception_id'] ) ? Perception::find( (int) $draft['state']['perception_id'] ) : null;
		if ( null === $perception ) {
			Response::redirect( '/onboarding/' . $id . '/analyze' );
		}
		$r = $perception['result'];

		$data = array(
			'name'            => $this->text( 'name', 160 ),
			'business_model'  => sanitize_key( $this->request->input( 'business_model', 'other' ) ),
			'legal_name'      => $this->text( 'legal_name', 200 ),
			'cui'             => strtoupper( str_replace( ' ', '', $this->text( 'cui', 20 ) ) ),
			'reg_com'         => strtoupper( str_replace( ' ', '', $this->text( 'reg_com', 40 ) ) ),
			'billing_email'   => sanitize_email( (string) $this->request->input( 'billing_email', '' ) ),
			'phone'           => $this->text( 'phone', 40 ),
			'service_area'    => $this->text( 'service_area', 255 ),
			'description'     => $this->textarea( 'description', 5000 ),
			'target_audience' => $this->textarea( 'target_audience', 3000 ),
			'status'          => 'active',
		);
		$v = new Validator();
		$v->required( 'name', $data['name'] )
			->in( 'business_model', $data['business_model'], array_keys( ov_business_models() ) )
			->pattern( 'cui', $data['cui'], '/^(RO)?[0-9]{2,10}$/', 'CUI invalid (ex. RO12345678).' )
			->pattern( 'reg_com', $data['reg_com'], '/^[JFC][0-9]{1,2}\/[0-9]{1,8}\/[0-9]{4}$|^J[0-9]{4}[0-9]{6,}[A-Z0-9]*$/', 'Nr. Reg. Com. invalid (ex. J40/1234/2020).' );

		// Services: kept AI services (possibly renamed) + new ones; unchecked AI services are "rejected".
		$services = array();
		$rejected = array();
		foreach ( (array) ( $r['services'] ?? array() ) as $i => $s ) {
			$name = mb_substr( sanitize_text_field( (string) ( $this->request->input( 'svc_name', array() )[ $i ] ?? $s['name'] ) ), 0, 160 );
			if ( '1' === (string) ( $this->request->input( 'svc_keep', array() )[ $i ] ?? '0' ) && '' !== $name ) {
				$services[] = array(
					'name'        => $name,
					'description' => (string) $s['description'],
				);
			} else {
				$rejected[] = (string) $s['name'];
			}
		}
		foreach ( (array) $this->request->input( 'svc_new', array() ) as $name ) {
			$name = mb_substr( sanitize_text_field( is_string( $name ) ? $name : '' ), 0, 160 );
			if ( '' !== $name ) {
				$services[] = array(
					'name'        => $name,
					'description' => '',
				);
			}
		}
		if ( empty( $services ) ) {
			$v->add( 'services', 'Păstrați sau adăugați cel puțin un serviciu.' );
		}

		$verdicts = array();
		foreach ( array( 'business_model', 'summary', 'target_audience' ) as $field ) {
			$verdict  = sanitize_key( (string) $this->request->input( 'verdict_' . $field, 'ok' ) );
			$ai_value = 'summary' === $field ? (string) ( $r['summary'] ?? '' ) : (string) ( $r[ $field ]['value'] ?? '' );
			$value    = 'summary' === $field ? $data['description'] : (string) $data[ $field ];
			if ( 'na' !== $verdict && trim( $value ) !== trim( $ai_value ) && '' !== $ai_value ) {
				$verdict = 'corrected';
			}
			$verdicts[ $field ] = in_array( $verdict, array( 'ok', 'corrected', 'na' ), true ) ? $verdict : 'ok';
		}

		$areas_final    = Perception_Gaps::split_areas( $data['service_area'] );
		$rejected_areas = array();
		foreach ( (array) ( $r['service_areas'] ?? array() ) as $a ) {
			$found = false;
			foreach ( $areas_final as $f ) {
				$found = $found || Perception_Gaps::same( $f, (string) $a['name'] );
			}
			if ( ! $found ) {
				$rejected_areas[] = (string) $a['name'];
			}
		}
		$keys    = Perception::pillar_keys();
		$pillars = array_values( array_intersect( array_map( 'sanitize_key', (array) $this->request->input( 'pillars', array() ) ), array_keys( $keys ) ) );

		$cms = (array) $this->request->input( 'site_cms', array() );
		if ( $v->fails() ) {
			$this->back_with_errors( '/onboarding/' . $id . '/confirm', $v->errors(), array_merge( $data, array( 'pillars' => $pillars ) ) );
		}

		$user_id = (int) $this->user()['id'];
		$db      = Database::instance();
		$sites   = (array) ( $draft['state']['sites'] ?? array() );

		$business_id = (int) $db->transaction(
			static function () use ( $db, $data, $services, $sites, $cms, $user_id, $perception, $id, $verdicts, $rejected, $rejected_areas, $pillars ): int {
				$business_id = Businesses_Repository::create( $data, $user_id );
				foreach ( $services as $i => $s ) {
					Business_Children_Repository::create(
						'business_offerings',
						$business_id,
						array(
							'type'        => 'service',
							'name'        => $s['name'],
							'description' => '' !== $s['description'] ? $s['description'] : null,
							'priority'    => min( 9, $i + 1 ),
							'is_active'   => 1,
						)
					);
				}
				foreach ( $sites as $site ) {
					$root = (string) $site['root'];
					$host = (string) preg_replace( '/^www\./', '', strtolower( (string) parse_url( $root, PHP_URL_HOST ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
					if ( Sites_Repository::domain_exists( $business_id, $host ) ) {
						continue;
					}
					$kind    = 'other' === ( $cms[ $site['host'] ] ?? 'wordpress' ) ? 'other' : 'wordpress';
					$site_id = Sites_Repository::create(
						$business_id,
						array(
							'name'   => (string) $site['host'],
							'url'    => $root,
							'domain' => $host,
							'cms'    => $kind,
						),
						$user_id
					);
					if ( 'wordpress' === $kind ) {
						Sites_Repository::rotate_connector_secret( $site_id );
					}
					Sites_Repository::save_integration( $site_id, 'psi', $root . '/', 'active' );
				}
				$state = array(
					'draft_id'     => $id,
					'step'         => 'commercial',
					'skipped'      => array(),
					'completed_at' => null,
					'decisions'    => array(
						'fields'            => $verdicts,
						'rejected_services' => $rejected,
						'rejected_areas'    => $rejected_areas,
						'pillars'           => $pillars,
					),
				);
				$db->update( 'businesses', array( 'onboarding_state' => json_encode( $state, JSON_UNESCAPED_UNICODE ) ), array( 'id' => $business_id ) );
				$db->update(
					'business_perceptions',
					array(
						'business_id' => $business_id,
						'is_baseline' => 1,
					),
					array( 'id' => (int) $perception['id'] )
				);
				if ( null !== $perception['ai_request_id'] ) {
					$db->update( 'ai_requests', array( 'business_id' => $business_id ), array( 'id' => (int) $perception['ai_request_id'] ) );
				}
				$db->update(
					'onboarding_drafts',
					array(
						'business_id' => $business_id,
						'status'      => 'converted',
					),
					array( 'id' => $id )
				);
				return $business_id;
			}
		);

		$gaps  = Perception_Gaps::compute( $business_id, (int) $perception['id'] );
		$cards = Perception_Gaps::cards( $business_id, (int) $perception['id'] );
		Audit_Log::record(
			'business.create',
			'business',
			$business_id,
			array(
				'name'    => $data['name'],
				'via'     => 'onboarding',
				'clarity' => $gaps['score'],
				'cards'   => $cards,
			),
			$business_id
		);
		$this->redirect_with( '/onboarding/' . $id . '/commercial', 'success', sprintf( 'Afacerea a fost creată. Scor de claritate: %d%%; %d recomandări generate din diferențe.', $gaps['score'], $cards ) );
	}

	/**
	 * Step 4: USP, rules, commercial data.
	 *
	 * @return void
	 */
	public function commercial_view(): void {
		[ $draft, $business ] = $this->with_business();
		$offerings            = Business_Children_Repository::all( 'business_offerings', (int) $business['id'] );
		$this->render(
			'onboarding/commercial',
			$this->base( $draft, 'commercial', $business ) + array(
				'usp'      => Usp_Builder::propose( $business, $offerings ),
				'rules'    => Business_Children_Repository::all( 'business_rules', (int) $business['id'] ),
				'contacts' => Business_Children_Repository::all( 'business_contacts', (int) $business['id'] ),
				'errors'   => $this->pull_errors(),
			)
		);
	}

	/**
	 * Step 4: saves.
	 *
	 * @return void
	 */
	public function commercial(): void {
		[ $draft, $business ] = $this->with_business();
		$bid                  = (int) $business['id'];
		$avg                  = str_replace( array( ' ', ',' ), array( '', '.' ), $this->text( 'avg_job_value', 20 ) );
		$capacity             = $this->text( 'daily_capacity', 6 );
		$update               = array(
			'usp_text'       => $this->textarea( 'usp_text', 2000 ),
			'avg_job_value'  => is_numeric( $avg ) ? round( (float) $avg, 2 ) : null,
			'daily_capacity' => '' !== $capacity && ctype_digit( $capacity ) ? min( 65535, (int) $capacity ) : null,
		);
		Businesses_Repository::update( $bid, $update );

		foreach ( array_filter( array_map( 'trim', explode( "\n", $this->textarea( 'rules', 5000 ) ) ) ) as $rule ) {
			Business_Children_Repository::create(
				'business_rules',
				$bid,
				array(
					'source'     => 'manual',
					'rule_text'  => mb_substr( $rule, 0, 1000 ),
					'is_active'  => 1,
					'created_by' => (int) $this->user()['id'],
				)
			);
		}
		$contact = $this->text( 'contact_name', 120 );
		if ( '' !== $contact ) {
			Business_Children_Repository::create(
				'business_contacts',
				$bid,
				array(
					'name'       => $contact,
					'job_title'  => $this->text( 'contact_role', 120 ) ?: null,
					'email'      => sanitize_email( (string) $this->request->input( 'contact_email', '' ) ) ?: null,
					'phone'      => $this->text( 'contact_phone', 40 ) ?: null,
					'is_primary' => 1,
				)
			);
		}
		$this->set_step( $bid, 'plugin' );
		Audit_Log::record( 'onboarding.commercial', 'business', $bid, array(), $bid );
		Response::redirect( '/onboarding/' . (int) $draft['id'] . '/plugin' );
	}

	/**
	 * Step 5: plugin per site.
	 *
	 * @return void
	 */
	public function plugin_view(): void {
		[ $draft, $business ] = $this->with_business();
		$this->render(
			'onboarding/plugin',
			$this->base( $draft, 'plugin', $business ) + array(
				'sites' => $this->site_status( (int) $business['id'] ),
			)
		);
	}

	/**
	 * AJAX: connector status of every site.
	 *
	 * @return void
	 */
	public function status(): void {
		[ , $business ] = $this->with_business();
		Response::json( array( 'sites' => $this->site_status( (int) $business['id'] ) ) );
	}

	/**
	 * Step 6: Google integrations per site.
	 *
	 * @return void
	 */
	public function google_view(): void {
		[ $draft, $business ] = $this->with_business();
		$candidates           = Google_Matcher::candidates();
		$sites                = array();
		foreach ( $this->site_status( (int) $business['id'] ) as $site ) {
			$site['integrations'] = Sites_Repository::integrations( (int) $site['id'] );
			$site['suggestions']  = Google_Matcher::suggest( (string) $site['url'], $candidates );
			$site['auto']         = 0;
			foreach ( $site['suggestions'] as $provider => $s ) {
				if ( null !== $s['match'] && $s['score'] >= Google_Matcher::AUTO_SCORE && '' === (string) ( $site['integrations'][ $provider ]['external_id'] ?? '' ) ) {
					++$site['auto'];
				}
			}
			$sites[] = $site;
		}
		$this->render(
			'onboarding/google',
			$this->base( $draft, 'google', $business ) + array(
				'sites'      => $sites,
				'providers'  => Sites_Repository::PROVIDERS,
				'accounts'   => \OptiVio\Google\Google_Client::accounts(),
				'candidates' => $candidates,
				'discovered' => Google_Matcher::has_discovery(),
			)
		);
	}

	/**
	 * Re-lists the properties of every connected Google account.
	 *
	 * @return void
	 */
	public function google_discover(): void {
		[ $draft ] = $this->with_business();
		$errors    = array();
		foreach ( \OptiVio\Google\Google_Client::accounts() as $account ) {
			try {
				$result = \OptiVio\Google\Google_Discovery::run( (int) $account['id'] );
				foreach ( $result['errors'] as $provider => $message ) {
					$errors[] = $account['account_email'] . ' / ' . ( Sites_Repository::PROVIDERS[ $provider ][0] ?? $provider ) . ': ' . $message;
				}
			} catch ( \Throwable $e ) {
				$errors[] = $account['account_email'] . ': ' . $e->getMessage();
			}
		}
		$back = '/onboarding/' . (int) $draft['id'] . '/google';
		if ( ! empty( $errors ) ) {
			$this->redirect_with( $back, 'warning', 'Proprietăți actualizate, cu erori: ' . mb_substr( implode( ' · ', $errors ), 0, 600 ) );
		}
		$this->redirect_with( $back, 'success', 'Lista proprietăților Google a fost actualizată.' );
	}

	/**
	 * Connects (or disconnects) one Google service of a site — Site Kit style.
	 *
	 * @return void
	 */
	public function google_connect(): void {
		[ $draft, $business ] = $this->with_business();
		$site                 = $this->business_site( (int) $business['id'] );
		$provider             = $this->request->param_key( 'provider' );
		$back                 = '/onboarding/' . (int) $draft['id'] . '/google';
		if ( ! in_array( $provider, Google_Matcher::PROVIDERS, true ) ) {
			throw new Http_Exception( 404 );
		}
		$value = (string) $this->request->input( 'property', '' );
		if ( '' === $value ) {
			Sites_Repository::save_integration( (int) $site['id'], $provider, null, 'not_configured' );
			Audit_Log::record( 'site.integration_update', 'site', (int) $site['id'], array( 'provider' => $provider, 'status' => 'not_configured', 'via' => 'onboarding' ), (int) $business['id'] );
			$this->redirect_with( $back, 'success', Sites_Repository::PROVIDERS[ $provider ][0] . ' a fost deconectat pentru ' . $site['name'] . '.' );
		}
		[ $credential_id, $property ] = array_pad( explode( '|', $value, 2 ), 2, '' );
		$candidate                    = Google_Matcher::find( $provider, absint( $credential_id ), (string) $property );
		if ( null === $candidate ) {
			$this->redirect_with( $back, 'error', 'Proprietatea aleasă nu este accesibilă contului Google. Reîmprospătați lista.' );
		}
		$this->connect_integration( $site, (int) $business['id'], $provider, $candidate );
		$this->redirect_with( $back, 'success', Sites_Repository::PROVIDERS[ $provider ][0] . ' conectat pentru ' . $site['name'] . ': ' . $candidate['label'] . '. Prima sincronizare a pornit.' );
	}

	/**
	 * Connects every confidently matched service of a site in one click.
	 *
	 * @return void
	 */
	public function google_auto(): void {
		[ $draft, $business ] = $this->with_business();
		$site                 = $this->business_site( (int) $business['id'] );
		$current              = Sites_Repository::integrations( (int) $site['id'] );
		$done                 = array();
		foreach ( Google_Matcher::suggest( (string) $site['url'], Google_Matcher::candidates() ) as $provider => $s ) {
			if ( null === $s['match'] || $s['score'] < Google_Matcher::AUTO_SCORE || '' !== (string) ( $current[ $provider ]['external_id'] ?? '' ) ) {
				continue;
			}
			$this->connect_integration( $site, (int) $business['id'], $provider, $s['match'] );
			$done[] = Sites_Repository::PROVIDERS[ $provider ][0];
		}
		$this->redirect_with(
			'/onboarding/' . (int) $draft['id'] . '/google',
			empty( $done ) ? 'warning' : 'success',
			empty( $done ) ? 'Nu am găsit servicii de conectat automat pentru ' . $site['name'] . '.' : 'Conectat pentru ' . $site['name'] . ': ' . implode( ', ', $done ) . '. Prima sincronizare a pornit.'
		);
	}

	/**
	 * Saves the integration (account + property) and starts its first sync.
	 *
	 * @param array  $site        Site.
	 * @param int    $business_id Business.
	 * @param string $provider    Provider.
	 * @param array  $candidate   Candidate (credential_id, id, label).
	 * @return void
	 */
	private function connect_integration( array $site, int $business_id, string $provider, array $candidate ): void {
		$site_id = (int) $site['id'];
		Sites_Repository::save_integration( $site_id, $provider, (string) $candidate['id'], 'active' );
		Database::instance()->query(
			'UPDATE site_integrations SET credential_id = ?, settings = NULL WHERE site_id = ? AND provider = ?',
			array( (int) $candidate['credential_id'], $site_id, $provider )
		);
		\OptiVio\Google\Google_Sync::dispatch( $site_id, $provider, $business_id, 'connect-' . gmdate( 'YmdHis' ), (int) $this->user()['id'] );
		Audit_Log::record(
			'site.integration_update',
			'site',
			$site_id,
			array(
				'provider' => $provider,
				'status'   => 'active',
				'via'      => 'onboarding',
			),
			$business_id
		);
		register_shutdown_function( array( \OptiVio\Services\Web_Cron::class, 'spawn' ) );
	}

	/**
	 * Site of the route that belongs to the business.
	 *
	 * @param int $business_id Business.
	 * @return array
	 * @throws Http_Exception 404.
	 */
	private function business_site( int $business_id ): array {
		$site = Sites_Repository::find( $this->request->param_int( 'site_id' ) );
		if ( null === $site || (int) $site['business_id'] !== $business_id ) {
			throw new Http_Exception( 404 );
		}
		return $site;
	}

	/**
	 * Moves to the next step (continue / skip).
	 *
	 * @return void
	 */
	public function advance(): void {
		[ $draft, $business ] = $this->with_business();
		$from                 = $this->request->param_key( 'step' );
		$next                 = array(
			'plugin' => 'google',
			'google' => 'summary',
		);
		if ( ! isset( $next[ $from ] ) ) {
			throw new Http_Exception( 404 );
		}
		if ( '1' === (string) $this->request->input( 'skip', '0' ) ) {
			$state              = $this->business_state( $business );
			$state['skipped'][] = $from;
			$state['skipped']   = array_values( array_unique( $state['skipped'] ) );
			Database::instance()->update( 'businesses', array( 'onboarding_state' => json_encode( $state, JSON_UNESCAPED_UNICODE ) ), array( 'id' => (int) $business['id'] ) );
		}
		$this->set_step( (int) $business['id'], $next[ $from ] );
		Response::redirect( '/onboarding/' . (int) $draft['id'] . '/' . $next[ $from ] );
	}

	/**
	 * Step 7: summary.
	 *
	 * @return void
	 */
	public function summary_view(): void {
		[ $draft, $business ] = $this->with_business();
		$baseline             = Perception::baseline( (int) $business['id'] );
		$this->render(
			'onboarding/summary',
			$this->base( $draft, 'summary', $business ) + array(
				'readiness'  => Readiness_Score::for_business( (int) $business['id'] ),
				'perception' => $baseline,
				'gaps'       => null !== $baseline ? Perception_Gaps::for_perception( (int) $baseline['id'] ) : array(),
				'sites'      => $this->site_status( (int) $business['id'] ),
				'state'      => $this->business_state( $business ),
			)
		);
	}

	/**
	 * Step 7: finishes the onboarding and runs the deterministic engine once.
	 *
	 * @return void
	 */
	public function finish(): void {
		[ , $business ] = $this->with_business();
		$bid            = (int) $business['id'];
		$state          = $this->business_state( $business );
		$state['step']  = 'done';
		$state['completed_at'] = gmdate( 'c' );
		Database::instance()->update( 'businesses', array( 'onboarding_state' => json_encode( $state, JSON_UNESCAPED_UNICODE ) ), array( 'id' => $bid ) );
		try {
			Engine_Runner::run( $bid );
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		Audit_Log::record( 'onboarding.completed', 'business', $bid, array( 'skipped' => $state['skipped'] ), $bid );
		$this->redirect_with( '/businesses/' . $bid, 'success', 'Onboarding finalizat. Recomandările sunt în Action Cards.' );
	}

	/**
	 * Deletes a draft (the business, if already created, is kept).
	 *
	 * @return void
	 */
	public function destroy(): void {
		$draft = $this->draft();
		Database::instance()->delete( 'onboarding_drafts', array( 'id' => (int) $draft['id'] ) );
		Audit_Log::record( 'onboarding.draft_deleted', 'onboarding_draft', (int) $draft['id'], array( 'label' => $draft['label'] ) );
		$this->redirect_with( '/onboarding', 'success', 'Ciorna a fost ștearsă.' );
	}

	/**
	 * Re-analysis from outside for an existing business: new draft prefilled with the previous pages.
	 *
	 * @return void
	 */
	public function reanalyze(): void {
		$business_id = $this->request->param_int( 'id' );
		$business    = Businesses_Repository::find( $business_id );
		if ( null === $business ) {
			throw new Http_Exception( 404 );
		}
		$db    = Database::instance();
		$roots = $db->get_col( "SELECT url FROM sites WHERE business_id = ? AND status <> 'archived' ORDER BY id", array( $business_id ) );
		$roots = array_values( array_filter( array_map( array( Page_Discovery::class, 'site_root' ), $roots ) ) );
		if ( empty( $roots ) ) {
			$this->redirect_with( '/businesses/' . $business_id . '/perception', 'error', 'Afacerea nu are site-uri.' );
		}
		$id = Drafts::create( (int) $this->user()['id'], (string) $business['name'], array( 'mode' => 'reanalysis' ), $business_id );
		$this->discover_into( $id, $roots );

		// Keep the selection of the previous analysis when available.
		$prev = $db->get_col( 'SELECT p.url FROM onboarding_pages p JOIN onboarding_drafts d ON d.id = p.draft_id WHERE d.business_id = ? AND d.id <> ? AND p.selected = 1 ORDER BY d.id DESC LIMIT 100', array( $business_id, $id ) );
		if ( ! empty( $prev ) ) {
			$hashes = array_map(
				static function ( $u ): string {
					return hash( 'sha256', Content_Ingestor::normalize_url( (string) $u ) );
				},
				$prev
			);
			$db->query( 'UPDATE onboarding_pages SET selected = 0 WHERE draft_id = ?', array( $id ) );
			$db->query( 'UPDATE onboarding_pages SET selected = 1 WHERE draft_id = ? AND url_hash IN (' . Database::in_placeholders( $hashes ) . ')', array_merge( array( $id ), $hashes ) );
		}
		Response::redirect( '/onboarding/' . $id . '/pages' );
	}

	/**
	 * Discovers the pages of the given roots into a draft.
	 *
	 * @param int   $id    Draft.
	 * @param array $roots Site roots.
	 * @return void
	 */
	private function discover_into( int $id, array $roots ): void {
		@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- May be disabled on shared hosting.
		$draft  = (array) Drafts::find( $id );
		$sites  = (array) ( $draft['state']['sites'] ?? array() );
		$robots = (array) ( $draft['state']['robots'] ?? array() );
		$limit  = Drafts::limits()['site'];
		foreach ( $roots as $root ) {
			$found                    = Page_Discovery::discover( $root );
			$host                     = Page_Discovery::host_key( $found['root'] );
			$robots[ $host ]          = $found['disallow'];
			$sites[]                  = array(
				'root'    => $found['root'],
				'host'    => $host,
				'home_ok' => $found['home_ok'],
				'errors'  => $found['errors'],
				'found'   => count( $found['pages'] ),
			);
			$pages = Page_Discovery::preselect( $found['pages'], $limit );
			if ( empty( $pages ) ) {
				$pages = array(
					array(
						'url'      => $found['root'] . '/',
						'title'    => 'Pagina principală',
						'source'   => 'home',
						'type'     => 'home',
						'selected' => true,
					),
				);
			}
			Drafts::add_pages( $id, $found['root'], $pages );
		}
		Drafts::patch(
			$id,
			array(
				'sites'  => $sites,
				'robots' => $robots,
			)
		);
	}

	/**
	 * Loads the draft of the route.
	 *
	 * @return array
	 * @throws Http_Exception 404.
	 */
	private function draft(): array {
		$draft = Drafts::find( $this->request->param_int( 'id' ) );
		if ( null === $draft ) {
			throw new Http_Exception( 404 );
		}
		return $draft;
	}

	/**
	 * Steps 1–3 are closed once the business was created by this draft.
	 *
	 * @param array $draft Draft.
	 * @return void
	 */
	private function guard_pre_business( array $draft ): void {
		if ( 'converted' === $draft['status'] && null !== $draft['business_id'] && 'reanalysis' !== ( $draft['state']['mode'] ?? 'new' ) ) {
			Response::redirect( '/onboarding/' . (int) $draft['id'] . '/' . $this->current_step( $draft ) );
		}
		if ( 'converted' === $draft['status'] && 'reanalysis' === ( $draft['state']['mode'] ?? 'new' ) ) {
			Response::redirect( '/businesses/' . (int) $draft['business_id'] . '/perception' );
		}
	}

	/**
	 * Draft + business for steps 4–7.
	 *
	 * @return array{0:array,1:array}
	 */
	private function with_business(): array {
		$draft = $this->draft();
		if ( null === $draft['business_id'] || 'reanalysis' === ( $draft['state']['mode'] ?? 'new' ) ) {
			Response::redirect( '/onboarding/' . (int) $draft['id'] . '/' . $this->current_step( $draft ) );
		}
		$business = Businesses_Repository::find( (int) $draft['business_id'] );
		if ( null === $business ) {
			throw new Http_Exception( 404 );
		}
		return array( $draft, $business );
	}

	/**
	 * Current step of a draft.
	 *
	 * @param array $draft Draft.
	 * @return string
	 */
	private function current_step( array $draft ): string {
		if ( null !== $draft['business_id'] && 'reanalysis' !== ( $draft['state']['mode'] ?? 'new' ) ) {
			$business = Businesses_Repository::find( (int) $draft['business_id'] );
			$step     = (string) ( $this->business_state( (array) $business )['step'] ?? 'commercial' );
			return isset( self::STEPS[ $step ] ) ? $step : 'summary';
		}
		switch ( $draft['status'] ) {
			case 'fetching':
				return 'fetch';
			case 'fetched':
				return 'analyze';
			case 'analyzed':
				return 'confirm';
			default:
				return 'pages';
		}
	}

	/**
	 * Decoded onboarding state of a business.
	 *
	 * @param array $business Business.
	 * @return array
	 */
	private function business_state( array $business ): array {
		$state = (array) ( json_decode( (string) ( $business['onboarding_state'] ?? '' ), true ) ?: array() );
		return $state + array(
			'step'      => 'commercial',
			'skipped'   => array(),
			'decisions' => array(),
		);
	}

	/**
	 * Saves the current step of a business onboarding.
	 *
	 * @param int    $business_id Business.
	 * @param string $step        Step.
	 * @return void
	 */
	private function set_step( int $business_id, string $step ): void {
		$state         = $this->business_state( (array) Businesses_Repository::find( $business_id ) );
		$state['step'] = $step;
		Database::instance()->update( 'businesses', array( 'onboarding_state' => json_encode( $state, JSON_UNESCAPED_UNICODE ) ), array( 'id' => $business_id ) );
	}

	/**
	 * Sites with connector status and mirrored content count.
	 *
	 * @param int $business_id Business.
	 * @return array
	 */
	private function site_status( int $business_id ): array {
		return Database::instance()->get_results(
			"SELECT s.id, s.name, s.url, s.cms, s.connector_status, s.connector_last_seen_at, s.plugin_version, s.content_synced_at, s.connector_key_id IS NOT NULL AS has_key,
				(SELECT COUNT(*) FROM wp_content c WHERE c.site_id = s.id AND c.status = 'publish') AS content_count
			 FROM sites s WHERE s.business_id = ? AND s.status <> 'archived' ORDER BY s.id",
			array( $business_id )
		);
	}

	/**
	 * Common view data.
	 *
	 * @param array      $draft    Draft.
	 * @param string     $step     Step.
	 * @param array|null $business Business.
	 * @return array
	 */
	private function base( array $draft, string $step, ?array $business = null ): array {
		$reanalysis = 'reanalysis' === ( $draft['state']['mode'] ?? 'new' );
		return array(
			'page_title'    => $reanalysis ? 'Re-analiză percepție' : 'Afacere nouă (wizard)',
			'page_subtitle' => ( null !== $business ? $business['name'] . ' · ' : '' ) . 'Pasul ' . self::STEPS[ $step ][0] . ': ' . self::STEPS[ $step ][1],
			'draft'         => $draft,
			'step'          => $step,
			'steps'         => self::STEPS,
			'business'      => $business,
			'reanalysis'    => $reanalysis,
		);
	}
}
