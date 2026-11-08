<?php
/**
 * OptiVio — Action Cards: review, edit, approve & apply, reject (→ permanent rule), manual
 * completion, rollback, AI content on demand, image prompts and uploads.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use finfo;
use OptiVio\Actions\Analysis_Service;
use OptiVio\Actions\Card_Applier;
use OptiVio\Actions\Cards;
use OptiVio\Ai\Ai_Client;
use OptiVio\Ai\Prompts;
use OptiVio\Core\Database;
use OptiVio\Core\Http_Exception;
use OptiVio\Repositories\Businesses_Repository;
use OptiVio\Repositories\Settings_Repository;
use OptiVio\Security\Audit_Log;
use OptiVio\Security\Gate;
use OptiVio\Services\Remote_Operations;
use Throwable;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Actions controller.
 */
final class Actions_Controller extends Controller {

	private const MAX_IMAGE_BYTES = 8388608;
	private const IMAGE_TYPES     = array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/webp' => 'webp',
	);

	/**
	 * Cards list.
	 *
	 * @return void
	 */
	public function index(): void {
		$business = $this->business();
		$status   = sanitize_key( $this->request->query( 'status', 'open' ) );
		$category = sanitize_key( $this->request->query( 'category', '' ) );
		$where    = array( 'business_id = ?' );
		$params   = array( (int) $business['id'] );
		if ( 'open' === $status ) {
			$where[] = "status IN ('proposed','approved','failed')";
		} elseif ( isset( Cards::STATUSES[ $status ] ) ) {
			$where[]  = 'status = ?';
			$params[] = $status;
		}
		if ( isset( Cards::CATEGORIES[ $category ] ) ) {
			$where[]  = 'category = ?';
			$params[] = $category;
		}
		$this->render(
			'actions/index',
			array(
				'page_title'    => 'Action Cards',
				'page_subtitle' => (string) $business['name'] . ' · propuneri de aprobat, editat sau respins',
				'business'      => $business,
				'rows'          => Database::instance()->get_results( 'SELECT * FROM action_cards WHERE ' . implode( ' AND ', $where ) . ' ORDER BY FIELD(status, "failed", "approved", "proposed") DESC, priority ASC, impact_score DESC, id DESC LIMIT 200', $params ),
				'counts'        => Database::instance()->get_results( 'SELECT status, COUNT(*) total FROM action_cards WHERE business_id = ? GROUP BY status', array( (int) $business['id'] ) ),
				'status'        => $status,
				'category'      => $category,
			)
		);
	}

	/**
	 * Card detail + editor.
	 *
	 * @return void
	 */
	public function show(): void {
		$business = $this->business();
		$card     = $this->card( $business );
		$db       = Database::instance();
		$ctx      = array();
		foreach ( array( 'content_id', 'source_content_id', 'target_content_id' ) as $key ) {
			if ( ! empty( $card['effective'][ $key ] ) ) {
				$ctx[ $key ] = $db->get_row( 'SELECT c.id, c.site_id, c.title, c.url, m.seo_title_resolved, m.meta_description_resolved FROM wp_content c LEFT JOIN wp_seo_meta m ON m.content_id = c.id WHERE c.id = ?', array( (int) $card['effective'][ $key ] ) );
			}
		}
		$site = null !== $card['site_id'] ? $db->get_row( 'SELECT id, name, connector_status, connector_remote_url FROM sites WHERE id = ?', array( (int) $card['site_id'] ) ) : null;

		$this->render(
			'actions/show',
			array(
				'page_title'    => (string) $card['title'],
				'page_subtitle' => Cards::CATEGORIES[ $card['category'] ][0] . ' · ' . ( 'ai' === $card['origin'] ? 'propus de AI' : 'propus de motorul determinist' ),
				'card'          => $card,
				'context'       => $ctx,
				'site'          => $site,
				'images'        => $db->get_results( 'SELECT i.*, u.original_name, u.width, u.height FROM action_card_images i LEFT JOIN uploads u ON u.id = i.upload_id WHERE i.action_card_id = ? ORDER BY i.id', array( (int) $card['id'] ) ),
				'operations'    => $db->get_results( 'SELECT * FROM remote_operations WHERE action_card_id = ? ORDER BY id DESC', array( (int) $card['id'] ) ),
				'can_manage'    => Gate::can_access_business( (int) $business['id'], 'manage' ),
				'remote'        => Cards::CATEGORIES[ $card['category'] ][1],
				'ai_preflight'  => Ai_Client::preflight( (int) $business['id'], 'copy', mb_strlen( Prompts::card_system() ) + 6000 ),
				'confirm_ai'    => (bool) Settings_Repository::get( 'ai.confirm_before_call', true ),
				'errors'        => $this->pull_errors(),
			)
		);
	}

	/**
	 * Saves edits of the payload (validated) and the publish status.
	 *
	 * @return void
	 */
	public function update(): void {
		$business = $this->business( 'manage' );
		$card     = $this->open_card( $business );
		$raw      = $this->request->input( 'payload', array() );
		$raw      = is_array( $raw ) ? $raw : array();
		foreach ( array( 'keywords', 'changes', 'steps' ) as $list ) {
			if ( isset( $raw[ $list ] ) && is_string( $raw[ $list ] ) ) {
				$raw[ $list ] = preg_split( '/\R/', $raw[ $list ] );
			}
		}
		if ( isset( $raw['hours'] ) && is_string( $raw['hours'] ) ) {
			$raw['hours'] = array_map( 'intval', preg_split( '/[\s,;]+/', trim( $raw['hours'] ) ) );
		}
		$merged = array_merge( $card['effective'], $raw );
		if ( isset( $card['effective']['images'] ) ) {
			$merged['images'] = $card['effective']['images'];
		}
		$payload = Cards::payload( (int) $business['id'], (string) $card['category'], $merged );
		if ( null === $payload ) {
			$this->redirect_with( '/actions/' . (int) $card['id'], 'error', 'Datele introduse nu sunt valide pentru acest tip de card.' );
		}
		Database::instance()->update(
			'action_cards',
			array(
				'edited_payload' => json_encode( $payload, JSON_UNESCAPED_UNICODE ),
				'publish_status' => 'publish' === sanitize_key( $this->request->input( 'publish_status', 'draft' ) ) ? 'publish' : 'draft',
			),
			array( 'id' => (int) $card['id'] )
		);
		Audit_Log::record( 'card.edit', 'action_card', (int) $card['id'], array(), (int) $business['id'] );
		$this->redirect_with( '/actions/' . (int) $card['id'], 'success', 'Modificările au fost salvate. Aplicarea pe site se face doar la aprobare.' );
	}

	/**
	 * Approves and applies (remote) the card.
	 *
	 * @return void
	 */
	public function apply(): void {
		$business = $this->business( 'manage' );
		$card     = $this->open_card( $business );
		$result   = Card_Applier::apply( $card, (int) $this->user()['id'] );
		Audit_Log::record( $result['ok'] ? 'card.apply' : 'card.apply_failed', 'action_card', (int) $card['id'], array( 'error' => $result['error'] ?? null ), (int) $business['id'] );
		$this->redirect_with( '/actions/' . (int) $card['id'], $result['ok'] ? 'success' : 'error', $result['ok'] ? 'Propunerea a fost aprobată și aplicată pe site (cu snapshot pentru rollback).' : 'Aplicarea a eșuat: ' . $result['error'] );
	}

	/**
	 * Marks a manual card as applied.
	 *
	 * @return void
	 */
	public function manual(): void {
		$business = $this->business( 'manage' );
		$card     = $this->open_card( $business );
		Card_Applier::mark_manual( $card, (int) $this->user()['id'], $this->text( 'note', 500 ) );
		Audit_Log::record( 'card.manual', 'action_card', (int) $card['id'], array(), (int) $business['id'] );
		$this->redirect_with( '/actions/' . (int) $card['id'], 'success', 'Marcat ca aplicat.' );
	}

	/**
	 * Rejects with a mandatory reason (stored as a permanent business rule).
	 *
	 * @return void
	 */
	public function reject(): void {
		$business = $this->business( 'manage' );
		$card     = $this->open_card( $business );
		$reason   = $this->textarea( 'reason', 1000 );
		if ( mb_strlen( $reason ) < 5 ) {
			$this->redirect_with( '/actions/' . (int) $card['id'], 'error', 'Motivul respingerii este obligatoriu — devine regulă permanentă pentru analizele viitoare.' );
		}
		Card_Applier::reject( $card, (int) $this->user()['id'], $reason );
		Audit_Log::record( 'card.reject', 'action_card', (int) $card['id'], array(), (int) $business['id'] );
		$this->redirect_with( '/actions', 'success', 'Propunerea a fost respinsă; motivul a fost salvat ca regulă permanentă a afacerii.' );
	}

	/**
	 * 1-click rollback of an applied card.
	 *
	 * @return void
	 */
	public function rollback(): void {
		$business = $this->business( 'manage' );
		$card     = $this->card( $business );
		if ( 'applied' !== $card['status'] ) {
			$this->redirect_with( '/actions/' . (int) $card['id'], 'error', 'Doar cardurile aplicate pot fi anulate.' );
		}
		$result = Card_Applier::rollback( $card, (int) $this->user()['id'] );
		Audit_Log::record( 'card.rollback', 'action_card', (int) $card['id'], array( 'ok' => $result['ok'] ), (int) $business['id'] );
		$this->redirect_with( '/actions/' . (int) $card['id'], $result['ok'] ? 'success' : 'error', $result['ok'] ? 'Rollback efectuat: versiunea anterioară a fost restaurată pe site.' : 'Rollback eșuat: ' . $result['error'] );
	}

	/**
	 * Fills the card content with AI (copywriting profile), on demand.
	 *
	 * @return void
	 */
	public function generate(): void {
		$business = $this->business( 'manage' );
		$card     = $this->open_card( $business );
		if ( (bool) Settings_Repository::get( 'ai.confirm_before_call', true ) && '1' !== (string) $this->request->input( 'confirm', '0' ) ) {
			$this->redirect_with( '/actions/' . (int) $card['id'], 'warning', 'Bifați confirmarea costului estimat.' );
		}
		try {
			Analysis_Service::fill_card( $card, (int) $this->user()['id'] );
		} catch ( Throwable $e ) {
			$this->redirect_with( '/actions/' . (int) $card['id'], 'error', $e->getMessage() );
		}
		Audit_Log::record( 'ai.card_content', 'action_card', (int) $card['id'], array(), (int) $business['id'] );
		$this->redirect_with( '/actions/' . (int) $card['id'], 'success', 'Conținutul a fost generat. Verificați și editați înainte de aprobare.' );
	}

	/**
	 * Adds an image slot (prompt) to a content card.
	 *
	 * @return void
	 */
	public function image_add(): void {
		$business  = $this->business( 'manage' );
		$card      = $this->open_card( $business );
		$placement = sanitize_key( $this->request->input( 'placement', '' ) );
		if ( ! preg_match( '/^(featured|inline_[1-5])$/', $placement ) || ! in_array( $card['category'], array( 'new_article', 'new_landing' ), true ) ) {
			$this->redirect_with( '/actions/' . (int) $card['id'], 'error', 'Poziție invalidă (featured sau inline_1…inline_5).' );
		}
		Database::instance()->insert(
			'action_card_images',
			array(
				'action_card_id' => (int) $card['id'],
				'placement'      => $placement,
				'prompt_text'    => $this->textarea( 'prompt', 2000 ),
				'alt_text'       => $this->text( 'alt', 255 ),
			)
		);
		$this->redirect_with( '/actions/' . (int) $card['id'], 'success', 'Imaginea a fost adăugată în plan.' );
	}

	/**
	 * Uploads the image for a slot (JPEG/PNG/WebP, ≤ 8 MB, stored outside the web root).
	 *
	 * @return void
	 * @throws Http_Exception 404.
	 */
	public function image_upload(): void {
		$business = $this->business( 'manage' );
		$card     = $this->open_card( $business );
		$db       = Database::instance();
		$slot     = $db->get_row( 'SELECT * FROM action_card_images WHERE id = ? AND action_card_id = ?', array( $this->request->param_int( 'image_id' ), (int) $card['id'] ) );
		if ( null === $slot ) {
			throw Http_Exception::not_found();
		}
		$file = $this->request->file( 'image' );
		$back = '/actions/' . (int) $card['id'];
		if ( null === $file || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			$this->redirect_with( $back, 'error', 'Selectați o imagine.' );
		}
		if ( (int) $file['size'] > self::MAX_IMAGE_BYTES ) {
			$this->redirect_with( $back, 'error', 'Imaginea depășește 8 MB.' );
		}
		$mime = ( new finfo( FILEINFO_MIME_TYPE ) )->file( (string) $file['tmp_name'] );
		$info = @getimagesize( (string) $file['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! isset( self::IMAGE_TYPES[ $mime ] ) || false === $info || $info['mime'] !== $mime ) {
			$this->redirect_with( $back, 'error', 'Sunt acceptate doar imagini JPEG, PNG sau WebP valide.' );
		}

		$stored = bin2hex( random_bytes( 30 ) ) . '.' . self::IMAGE_TYPES[ $mime ];
		if ( ! move_uploaded_file( (string) $file['tmp_name'], OPTIVIO_STORAGE . '/uploads/' . $stored ) ) {
			$this->redirect_with( $back, 'error', 'Imaginea nu a putut fi salvată.' );
		}
		$upload_id = $db->insert(
			'uploads',
			array(
				'business_id'   => (int) $business['id'],
				'original_name' => mb_substr( sanitize_text_field( (string) $file['name'] ), 0, 190 ),
				'stored_name'   => $stored,
				'mime'          => $mime,
				'size_bytes'    => (int) $file['size'],
				'sha256'        => hash_file( 'sha256', OPTIVIO_STORAGE . '/uploads/' . $stored ),
				'width'         => (int) $info[0],
				'height'        => (int) $info[1],
				'uploaded_by'   => (int) $this->user()['id'],
			)
		);
		$db->update( 'action_card_images', array( 'upload_id' => $upload_id ), array( 'id' => (int) $slot['id'] ) );
		Audit_Log::record( 'card.image_upload', 'action_card', (int) $card['id'], array( 'upload_id' => $upload_id ), (int) $business['id'] );
		$this->redirect_with( $back, 'success', 'Imaginea a fost încărcată; va fi urcată în WordPress la aplicare.' );
	}

	/**
	 * Serves an uploaded image (access checked through its business).
	 *
	 * @return void
	 * @throws Http_Exception 404.
	 */
	public function upload_file(): void {
		$row = Database::instance()->get_row( 'SELECT * FROM uploads WHERE id = ?', array( $this->request->param_int( 'id' ) ) );
		if ( null === $row ) {
			throw Http_Exception::not_found();
		}
		Gate::authorize_business( (int) $row['business_id'] );
		$path = OPTIVIO_STORAGE . '/uploads/' . basename( (string) $row['stored_name'] );
		if ( ! is_file( $path ) || ! isset( self::IMAGE_TYPES[ $row['mime'] ] ) ) {
			throw Http_Exception::not_found();
		}
		header( 'Content-Type: ' . $row['mime'] );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Content-Disposition: inline; filename="image.' . self::IMAGE_TYPES[ $row['mime'] ] . '"' );
		header( 'Cache-Control: private, max-age=3600' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Active business (authorized).
	 *
	 * @param string $permission view|manage.
	 * @return array
	 */
	private function business( string $permission = 'view' ): array {
		$id = Gate::active_business_id();
		if ( 0 === $id ) {
			$this->redirect_with( '/keywords', 'warning', 'Selectați mai întâi afacerea activă.' );
		}
		Gate::authorize_business( $id, $permission );
		return (array) Businesses_Repository::find( $id );
	}

	/**
	 * Card of the business.
	 *
	 * @param array $business Business.
	 * @return array
	 * @throws Http_Exception 404.
	 */
	private function card( array $business ): array {
		$card = Cards::find( (int) $business['id'], $this->request->param_int( 'id' ) );
		if ( null === $card ) {
			throw Http_Exception::not_found();
		}
		return $card;
	}

	/**
	 * Card that can still be decided (proposed / approved / failed).
	 *
	 * @param array $business Business.
	 * @return array
	 */
	private function open_card( array $business ): array {
		$card = $this->card( $business );
		if ( ! in_array( $card['status'], array( 'proposed', 'approved', 'failed' ), true ) ) {
			$this->redirect_with( '/actions/' . (int) $card['id'], 'error', 'Cardul a fost deja decis.' );
		}
		return $card;
	}
}
