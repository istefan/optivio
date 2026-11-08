<?php
/**
 * OptiVio — Connector actions for a site: plugin download, ping, resync, mirrored content,
 * remote SEO edits (explicit user approval) and 1-click rollback.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Core\Database;
use OptiVio\Core\Http_Exception;
use OptiVio\Core\Response;
use OptiVio\Core\Validator;
use OptiVio\Repositories\Sites_Repository;
use OptiVio\Security\Audit_Log;
use OptiVio\Security\Gate;
use OptiVio\Services\Plugin_Packager;
use OptiVio\Services\Remote_Operations;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Site connector controller.
 */
final class Site_Connector_Controller extends Controller {

	/**
	 * Downloads the plugin zip with this site's credentials embedded (POST + CSRF + manage).
	 *
	 * @return void
	 */
	public function download(): void {
		[ $site, $business_id ] = $this->site( 'manage' );
		$creds                  = null !== $site['connector_key_id'] ? Sites_Repository::find_by_connector_key( (string) $site['connector_key_id'] ) : null;
		if ( null === $creds ) {
			$this->redirect_with( '/sites/' . (int) $site['id'], 'error', 'Generați mai întâi cheia conectorului.' );
		}

		$zip = Plugin_Packager::build( (string) $creds['connector_key_id'], (string) $creds['secret'] );
		Audit_Log::record( 'site.plugin_download', 'site', (int) $site['id'], array( 'key_id' => $creds['connector_key_id'] ), $business_id );

		$name = 'optivio-connector-' . preg_replace( '/[^a-z0-9.\-]/', '', (string) $site['domain'] ) . '.zip';
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . strlen( $zip ) );
		header( 'Cache-Control: no-store' );
		echo $zip; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary download.
		exit;
	}

	/**
	 * Tests the OptiVio → WordPress channel.
	 *
	 * @return void
	 */
	public function ping(): void {
		[ $site ] = $this->site( 'manage' );
		$result   = Remote_Operations::execute( (int) $site['id'], 'ping', array(), (int) $this->user()['id'], array( 'object_type' => 'site' ) );
		$this->redirect_with(
			'/sites/' . (int) $site['id'],
			$result['ok'] ? 'success' : 'error',
			$result['ok']
				? sprintf( 'Conexiune OK — plugin %s, WordPress %s.', $result['response']['plugin_version'] ?? '?', $result['response']['wp_version'] ?? '?' )
				: 'Conexiunea către WordPress a eșuat: ' . $result['error']
		);
	}

	/**
	 * Asks the plugin for a full content resync.
	 *
	 * @return void
	 */
	public function resync(): void {
		[ $site ] = $this->site( 'manage' );
		$result   = Remote_Operations::execute( (int) $site['id'], 'resync', array( 'all' => true ), (int) $this->user()['id'], array( 'object_type' => 'site' ) );
		$this->redirect_with( '/sites/' . (int) $site['id'] . '/content', $result['ok'] ? 'success' : 'error', $result['ok'] ? 'Resincronizarea completă a fost pornită pe site (rulează în fundal prin WP-Cron).' : 'Eroare: ' . $result['error'] );
	}

	/**
	 * Mirrored content list.
	 *
	 * @return void
	 */
	public function content(): void {
		[ $site ] = $this->site( 'view' );
		$type     = sanitize_key( $this->request->query( 'type', '' ) );
		$term     = mb_substr( sanitize_text_field( $this->request->query( 'q', '' ) ), 0, 100 );
		$page     = max( 1, absint( $this->request->query( 'page', 1 ) ) );
		$per      = 50;

		$where  = array( 'c.site_id = ?' );
		$params = array( (int) $site['id'] );
		if ( 'term' === $type ) {
			$where[] = "c.object_type = 'term'";
		} elseif ( '' !== $type ) {
			$where[]  = "c.object_type = 'post' AND c.subtype = ?";
			$params[] = $type;
		}
		if ( '' !== $term ) {
			$where[]  = '(c.title LIKE ? OR c.url LIKE ?)';
			$like     = '%' . str_replace( array( '\\', '%', '_' ), array( '\\\\', '\\%', '\\_' ), $term ) . '%';
			$params[] = $like;
			$params[] = $like;
		}
		$sql_where = implode( ' AND ', $where );
		$db        = Database::instance();
		$total     = (int) $db->get_var( 'SELECT COUNT(*) FROM wp_content c WHERE ' . $sql_where, $params );
		$rows      = $db->get_results(
			'SELECT c.*, m.seo_title, m.seo_title_resolved, m.meta_description, m.meta_description_resolved, m.focus_keyword, m.robots_noindex
			 FROM wp_content c LEFT JOIN wp_seo_meta m ON m.content_id = c.id
			 WHERE ' . $sql_where . ' ORDER BY c.object_type ASC, c.subtype ASC, c.title ASC LIMIT ? OFFSET ?',
			array_merge( $params, array( $per, ( $page - 1 ) * $per ) )
		);
		$types = $db->get_col( "SELECT DISTINCT subtype FROM wp_content WHERE site_id = ? AND object_type = 'post' ORDER BY subtype", array( (int) $site['id'] ) );

		$this->render(
			'sites/content',
			array(
				'page_title'    => 'Conținut & SEO',
				'page_subtitle' => (string) $site['name'] . ' · oglindă din WordPress (Yoast SEO)',
				'site'          => $site,
				'rows'          => $rows,
				'total'         => $total,
				'page'          => $page,
				'pages'         => max( 1, (int) ceil( $total / $per ) ),
				'type'          => $type,
				'term'          => $term,
				'types'         => $types,
				'can_manage'    => Gate::can_access_business( (int) $site['business_id'], 'manage' ),
			)
		);
	}

	/**
	 * Content detail with SEO editor and operations history.
	 *
	 * @return void
	 */
	public function content_show(): void {
		[ $site ] = $this->site( 'view' );
		$content  = $this->content_row( (int) $site['id'] );
		$db       = Database::instance();

		$this->render(
			'sites/content-show',
			array(
				'page_title'    => (string) ( $content['title'] ?? 'Conținut' ),
				'page_subtitle' => (string) $site['name'] . ' · ' . $content['subtype'] . ' #' . $content['wp_id'],
				'site'          => $site,
				'content'       => $content,
				'images'        => $db->get_results( 'SELECT src, alt FROM wp_images WHERE content_id = ? ORDER BY id LIMIT 100', array( (int) $content['id'] ) ),
				'links_out'     => $db->get_results(
					'SELECT il.target_url, il.anchor_text, t.title AS target_title FROM internal_links il LEFT JOIN wp_content t ON t.id = il.target_content_id
					 WHERE il.source_content_id = ? ORDER BY il.id LIMIT 100',
					array( (int) $content['id'] )
				),
				'links_in'      => $db->get_results(
					'SELECT DISTINCT s.id, s.title, s.url, il.anchor_text FROM internal_links il JOIN wp_content s ON s.id = il.source_content_id
					 WHERE il.site_id = ? AND il.target_url_hash = ? AND s.id <> ? LIMIT 100',
					array( (int) $site['id'], $content['url_hash'], (int) $content['id'] )
				),
				'history'       => Remote_Operations::history( (int) $site['id'], (int) $content['id'], 20 ),
				'can_manage'    => Gate::can_access_business( (int) $site['business_id'], 'manage' ),
				'errors'        => $this->pull_errors(),
			)
		);
	}

	/**
	 * Applies SEO / title changes on the live site (explicit approval = this submit).
	 *
	 * @return void
	 */
	public function content_update(): void {
		[ $site, $business_id ] = $this->site( 'manage' );
		$content                = $this->content_row( (int) $site['id'] );
		$back                   = '/sites/' . (int) $site['id'] . '/content/' . (int) $content['id'];

		$seo = array(
			'seo_title'        => $this->text( 'seo_title', 300 ),
			'meta_description' => $this->text( 'meta_description', 500 ),
			'focus_keyword'    => $this->text( 'focus_keyword', 190 ),
			'canonical'        => $this->text( 'canonical', 500 ),
			'noindex'          => '1' === (string) $this->request->input( 'noindex', '0' ),
		);
		$title = $this->text( 'title', 300 );

		$v = new Validator();
		$v->pattern( 'canonical', $seo['canonical'], '#^https?://\S+$#i', 'URL canonic invalid.' );
		if ( 'post' === $content['object_type'] ) {
			$v->required( 'title', $title, 'Titlul nu poate fi gol.' );
		}
		if ( $v->fails() ) {
			$this->back_with_errors( $back, $v->errors(), array_merge( $seo, array( 'title' => $title ) ) );
		}

		$context = array(
			'content_id'  => (int) $content['id'],
			'object_type' => (string) $content['object_type'],
			'taxonomy'    => 'term' === $content['object_type'] ? (string) $content['subtype'] : null,
		);
		$user_id = (int) $this->user()['id'];

		if ( 'post' === $content['object_type'] && $title !== (string) $content['title'] ) {
			$result = Remote_Operations::execute(
				(int) $site['id'],
				'update_post',
				array(
					'id'     => (int) $content['wp_id'],
					'fields' => array( 'title' => $title ),
					'seo'    => $seo,
				),
				$user_id,
				$context
			);
		} else {
			$result = Remote_Operations::execute(
				(int) $site['id'],
				'update_seo_meta',
				array(
					'object_type' => (string) $content['object_type'],
					'id'          => (int) $content['wp_id'],
					'taxonomy'    => $context['taxonomy'],
					'fields'      => $seo,
				),
				$user_id,
				$context
			);
		}

		Audit_Log::record( $result['ok'] ? 'remote.apply' : 'remote.failed', 'remote_operation', $result['operation_id'], array( 'content_id' => (int) $content['id'] ), $business_id );
		$this->redirect_with(
			$back,
			$result['ok'] ? 'success' : 'error',
			$result['ok'] ? 'Modificarea a fost aplicată pe site. A fost salvat un snapshot pentru rollback.' : 'Modificarea nu a putut fi aplicată: ' . $result['error']
		);
	}

	/**
	 * Operations history of the site.
	 *
	 * @return void
	 */
	public function operations(): void {
		[ $site ] = $this->site( 'view' );
		$this->render(
			'sites/operations',
			array(
				'page_title'    => 'Modificări la distanță',
				'page_subtitle' => (string) $site['name'] . ' · istoric cu rollback',
				'site'          => $site,
				'history'       => Remote_Operations::history( (int) $site['id'], null, 100 ),
				'can_manage'    => Gate::can_access_business( (int) $site['business_id'], 'manage' ),
			)
		);
	}

	/**
	 * 1-click rollback of an applied operation.
	 *
	 * @return void
	 * @throws Http_Exception 404.
	 */
	public function rollback(): void {
		[ $site, $business_id ] = $this->site( 'manage' );
		$op                     = Database::instance()->get_row(
			'SELECT * FROM remote_operations WHERE id = ? AND site_id = ?',
			array( $this->request->param_int( 'op_id' ), (int) $site['id'] )
		);
		if ( null === $op ) {
			throw Http_Exception::not_found();
		}

		$result = Remote_Operations::rollback( $op, (int) $this->user()['id'] );
		Audit_Log::record( 'remote.rollback', 'remote_operation', (int) $op['id'], array( 'ok' => $result['ok'] ), $business_id );

		$back = null !== $op['content_id'] ? '/sites/' . (int) $site['id'] . '/content/' . (int) $op['content_id'] : '/sites/' . (int) $site['id'] . '/operations';
		$this->redirect_with( $back, $result['ok'] ? 'success' : 'error', $result['ok'] ? 'Rollback efectuat — versiunea anterioară a fost restaurată pe site.' : 'Rollback eșuat: ' . $result['error'] );
	}

	/**
	 * Loads and authorizes the site from {id}.
	 *
	 * @param string $permission view|manage.
	 * @return array{0:array,1:int}
	 */
	private function site( string $permission ): array {
		$site_id     = $this->request->param_int( 'id' );
		$business_id = Gate::authorize_site( $site_id, $permission );
		return array( (array) Sites_Repository::find( $site_id ), $business_id );
	}

	/**
	 * Loads a mirrored content row of the site (scoped by site id).
	 *
	 * @param int $site_id Site.
	 * @return array
	 * @throws Http_Exception 404.
	 */
	private function content_row( int $site_id ): array {
		$row = Database::instance()->get_row(
			'SELECT c.*, m.seo_title, m.seo_title_resolved, m.meta_description, m.meta_description_resolved, m.focus_keyword, m.canonical,
				m.robots_noindex, m.yoast_seo_score, m.yoast_read_score
			 FROM wp_content c LEFT JOIN wp_seo_meta m ON m.content_id = c.id WHERE c.id = ? AND c.site_id = ?',
			array( $this->request->param_int( 'content_id' ), $site_id )
		);
		if ( null === $row ) {
			throw Http_Exception::not_found();
		}
		return $row;
	}
}
