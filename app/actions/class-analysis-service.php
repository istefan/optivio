<?php
/**
 * OptiVio — On-demand AI work: deep multi-source analysis and filling a card's content.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Actions;

use OptiVio\Ai\Ai_Client;
use OptiVio\Ai\Prompts;
use OptiVio\Ai\Snapshot_Builder;
use OptiVio\Core\Database;
use OptiVio\Repositories\Business_Children_Repository as Children;
use RuntimeException;
use Throwable;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Analysis service.
 */
final class Analysis_Service {

	/**
	 * Runs the deep analysis. Returns [analysis_id, cards_created, cards_rejected_by_validation].
	 *
	 * @param int $business_id Business (authorized by caller).
	 * @param int $user_id     User (must be the authenticated actor of this POST).
	 * @return array{0:int,1:int,2:int}
	 * @throws RuntimeException On AI errors (analysis is stored with status error).
	 */
	public static function deep( int $business_id, int $user_id ): array {
		Ai_Client::assert_on_demand( $user_id );
		$db       = Database::instance();
		$snapshot = Snapshot_Builder::build( $business_id );
		$json     = (string) json_encode( $snapshot, JSON_UNESCAPED_UNICODE );
		$id       = $db->insert(
			'analyses',
			array(
				'business_id'    => $business_id,
				'type'           => 'deep',
				'status'         => 'running',
				'input_snapshot' => $json,
				'requested_by'   => $user_id,
			)
		);

		try {
			$result = Ai_Client::complete_json( $user_id, $business_id, 'reasoning', 'deep_analysis', Prompts::deep_system(), $json, array( 'analysis_id' => $id ) );
		} catch ( Throwable $e ) {
			$db->update(
				'analyses',
				array(
					'status'       => 'error',
					'output_text'  => $e->getMessage(),
					'completed_at' => Database::now(),
				),
				array( 'id' => $id )
			);
			throw new RuntimeException( $e->getMessage() );
		}

		$created = 0;
		$invalid = 0;
		foreach ( array_slice( (array) ( $result['data']['cards'] ?? array() ), 0, 30 ) as $raw ) {
			$card = is_array( $raw ) ? Cards::validate( $business_id, $raw ) : null;
			if ( null === $card ) {
				++$invalid;
				continue;
			}
			if ( null !== Cards::create( $business_id, $card, 'ai', $id ) ) {
				++$created;
			}
		}

		$db->update(
			'analyses',
			array(
				'status'        => 'done',
				'output_text'   => mb_substr( sanitize_textarea_field( (string) ( $result['data']['summary_markdown'] ?? '' ) ), 0, 60000 ),
				'ai_request_id' => $result['request_id'],
				'completed_at'  => Database::now(),
			),
			array( 'id' => $id )
		);
		return array( $id, $created, $invalid );
	}

	/**
	 * Fills / rewrites the content of a card with the copywriting profile; saved as edited payload.
	 *
	 * @param array $card    Card (Cards::find()).
	 * @param int   $user_id User.
	 * @return void
	 * @throws RuntimeException On AI or validation errors.
	 */
	public static function fill_card( array $card, int $user_id ): void {
		$business_id = (int) $card['business_id'];
		$db          = Database::instance();
		$b           = (array) $db->get_row( 'SELECT name, business_model, service_area, description, usp_text FROM businesses WHERE id = ?', array( $business_id ) );
		$context     = array(
			'business' => array(
				'name'         => $b['name'],
				'model'        => $b['business_model'],
				'service_area' => $b['service_area'],
				'description'  => mb_substr( (string) $b['description'], 0, 1500 ),
				'usp'          => $b['usp_text'],
			),
			'rules'    => Children::active_rules( $business_id ),
			'card'     => array(
				'category'  => $card['category'],
				'title'     => $card['title'],
				'rationale' => $card['rationale'],
				'payload'   => $card['effective'],
			),
		);
		$cid = (int) ( $card['effective']['content_id'] ?? 0 );
		if ( $cid > 0 ) {
			$context['page'] = $db->get_row(
				'SELECT c.title, c.url, c.headings, LEFT(b.body_html, 6000) body FROM wp_content c LEFT JOIN wp_content_bodies b ON b.content_id = c.id WHERE c.id = ?',
				array( $cid )
			);
		}

		$result  = Ai_Client::complete_json( $user_id, $business_id, 'copy', 'card_content', Prompts::card_system(), (string) json_encode( $context, JSON_UNESCAPED_UNICODE ), array( 'action_card_id' => (int) $card['id'] ) );
		$payload = Cards::payload( $business_id, (string) $card['category'], array_merge( $card['effective'], (array) ( $result['data']['payload'] ?? array() ) ) );
		if ( null === $payload ) {
			throw new RuntimeException( 'Răspunsul AI nu respectă formatul cardului.' );
		}
		$db->update( 'action_cards', array( 'edited_payload' => json_encode( $payload, JSON_UNESCAPED_UNICODE ) ), array( 'id' => (int) $card['id'] ) );
	}
}
