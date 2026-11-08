<?php
/**
 * OptiVio — Route table.
 *
 * Every route is authenticated by default. Business/site level authorization
 * (anti-IDOR) is enforced inside controllers through OptiVio\Security\Gate.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Controllers\Actions_Controller;
use OptiVio\Controllers\Analyses_Controller;
use OptiVio\Controllers\Audit_Controller;
use OptiVio\Controllers\Auth_Controller;
use OptiVio\Controllers\Backup_Controller;
use OptiVio\Controllers\Business_Ai_Controller;
use OptiVio\Controllers\Business_Children_Controller;
use OptiVio\Controllers\Business_Usp_Controller;
use OptiVio\Controllers\Calls_Controller;
use OptiVio\Controllers\Form_Entries_Controller;
use OptiVio\Controllers\Google_Controller;
use OptiVio\Controllers\Help_Controller;
use OptiVio\Controllers\Onboarding_Controller;
use OptiVio\Controllers\Perception_Controller;
use OptiVio\Controllers\Businesses_Controller;
use OptiVio\Controllers\Site_Connector_Controller;
use OptiVio\Controllers\Sites_Controller;
use OptiVio\Controllers\Strategy_Controller;
use OptiVio\Controllers\Connector_Api_Controller;
use OptiVio\Controllers\Context_Controller;
use OptiVio\Controllers\Conversions_Controller;
use OptiVio\Controllers\Cron_Controller;
use OptiVio\Controllers\Dashboard_Controller;
use OptiVio\Controllers\Profile_Controller;
use OptiVio\Controllers\Search_Controller;
use OptiVio\Controllers\Settings_Controller;
use OptiVio\Controllers\Setup_Controller;
use OptiVio\Controllers\Users_Controller;
use OptiVio\Core\Router;

return static function ( Router $r ): void {
	$guest = array(
		'auth'  => false,
		'guest' => true,
	);
	$admin = array( 'role' => 'super_admin' );

	// Authentication & installation.
	$r->add( 'GET', '/login', array( Auth_Controller::class, 'show_login' ), $guest );
	$r->add( 'POST', '/login', array( Auth_Controller::class, 'login' ), $guest );
	$r->add( 'POST', '/logout', array( Auth_Controller::class, 'logout' ) );
	$r->add( 'GET', '/setup', array( Setup_Controller::class, 'show' ), $guest );
	$r->add( 'POST', '/setup', array( Setup_Controller::class, 'store' ), $guest );

	// Web cron (no cPanel): loopback endpoint authenticated by an automatic secret header, no session/CSRF.
	$r->add(
		'POST',
		'/cron/spawn',
		array( Cron_Controller::class, 'spawn' ),
		array(
			'auth' => false,
			'csrf' => false,
		)
	);
	// Browser fallback for hosts that block loopback requests.
	$r->add( 'POST', '/cron/tick', array( Cron_Controller::class, 'tick' ) );

	// OptiVio Connector machine API: HMAC signed, stateless (no session/CSRF).
	$machine = array(
		'auth' => false,
		'csrf' => false,
	);
	$r->add( 'POST', '/api/connector/v1/heartbeat', array( Connector_Api_Controller::class, 'heartbeat' ), $machine );
	$r->add( 'POST', '/api/connector/v1/events', array( Connector_Api_Controller::class, 'events' ), $machine );
	$r->add( 'POST', '/api/connector/v1/content', array( Connector_Api_Controller::class, 'content' ), $machine );

	// Dashboard & context.
	$r->add( 'GET', '/', array( Dashboard_Controller::class, 'index' ) );
	$r->add( 'GET', '/api/dashboard/chart', array( Dashboard_Controller::class, 'chart' ) );
	$r->add( 'POST', '/context/business', array( Context_Controller::class, 'switch_business' ) );
	$r->add( 'POST', '/notifications/read', array( Context_Controller::class, 'mark_notifications_read' ) );
	$r->add( 'GET', '/search', array( Search_Controller::class, 'index' ) );
	$r->add( 'GET', '/help', array( Help_Controller::class, 'index' ) );
	$r->add( 'GET', '/conversions', array( Conversions_Controller::class, 'index' ) );

	// Form entries (decrypted detail + spam override) and calls (Stage 3).
	$r->add( 'GET', '/forms/{id}', array( Form_Entries_Controller::class, 'show' ) );
	$r->add( 'POST', '/forms/{id}/spam', array( Form_Entries_Controller::class, 'override' ) );
	$r->add( 'GET', '/calls', array( Calls_Controller::class, 'index' ) );
	$r->add( 'POST', '/calls', array( Calls_Controller::class, 'store' ) );
	$r->add( 'GET', '/calls/import', array( Calls_Controller::class, 'import_form' ) );
	$r->add( 'POST', '/calls/import', array( Calls_Controller::class, 'import' ) );
	$r->add( 'GET', '/calls/{id}/edit', array( Calls_Controller::class, 'edit' ) );
	$r->add( 'POST', '/calls/{id}', array( Calls_Controller::class, 'update' ) );
	$r->add( 'POST', '/calls/{id}/delete', array( Calls_Controller::class, 'destroy' ) );

	// Businesses.
	$r->add( 'GET', '/businesses', array( Businesses_Controller::class, 'index' ) );
	$r->add( 'GET', '/businesses/new', array( Businesses_Controller::class, 'create' ), $admin );
	$r->add( 'POST', '/businesses', array( Businesses_Controller::class, 'store' ), $admin );
	$r->add( 'GET', '/businesses/{id}', array( Businesses_Controller::class, 'show' ) );
	$r->add( 'GET', '/businesses/{id}/delete', array( Businesses_Controller::class, 'confirm_delete' ), $admin );
	$r->add( 'POST', '/businesses/{id}/delete', array( Businesses_Controller::class, 'destroy' ), $admin );
	$r->add( 'GET', '/businesses/{id}/edit', array( Businesses_Controller::class, 'edit' ) );
	$r->add( 'POST', '/businesses/{id}', array( Businesses_Controller::class, 'update' ) );

	// Onboarding wizard + perception (Stage 8). The wizard is super admin only.
	$r->add( 'GET', '/onboarding', array( Onboarding_Controller::class, 'index' ), $admin );
	$r->add( 'POST', '/onboarding', array( Onboarding_Controller::class, 'start' ), $admin );
	$r->add( 'GET', '/onboarding/{id}', array( Onboarding_Controller::class, 'resume' ), $admin );
	$r->add( 'POST', '/onboarding/{id}/delete', array( Onboarding_Controller::class, 'destroy' ), $admin );
	$r->add( 'GET', '/onboarding/{id}/pages', array( Onboarding_Controller::class, 'pages' ), $admin );
	$r->add( 'POST', '/onboarding/{id}/pages', array( Onboarding_Controller::class, 'save_pages' ), $admin );
	$r->add( 'POST', '/onboarding/{id}/pages/{page_id}/retry', array( Onboarding_Controller::class, 'page_retry' ), $admin );
	$r->add( 'POST', '/onboarding/{id}/pages/{page_id}/exclude', array( Onboarding_Controller::class, 'page_exclude' ), $admin );
	$r->add( 'GET', '/onboarding/{id}/fetch', array( Onboarding_Controller::class, 'fetch_view' ), $admin );
	$r->add( 'POST', '/onboarding/{id}/fetch', array( Onboarding_Controller::class, 'fetch_step' ), $admin );
	$r->add( 'GET', '/onboarding/{id}/analyze', array( Onboarding_Controller::class, 'analyze_view' ), $admin );
	$r->add( 'POST', '/onboarding/{id}/analyze', array( Onboarding_Controller::class, 'analyze' ), $admin );
	$r->add( 'GET', '/onboarding/{id}/confirm', array( Onboarding_Controller::class, 'confirm_view' ), $admin );
	$r->add( 'POST', '/onboarding/{id}/confirm', array( Onboarding_Controller::class, 'confirm' ), $admin );
	$r->add( 'GET', '/onboarding/{id}/commercial', array( Onboarding_Controller::class, 'commercial_view' ), $admin );
	$r->add( 'POST', '/onboarding/{id}/commercial', array( Onboarding_Controller::class, 'commercial' ), $admin );
	$r->add( 'GET', '/onboarding/{id}/plugin', array( Onboarding_Controller::class, 'plugin_view' ), $admin );
	$r->add( 'GET', '/onboarding/{id}/status', array( Onboarding_Controller::class, 'status' ), $admin );
	$r->add( 'GET', '/onboarding/{id}/google', array( Onboarding_Controller::class, 'google_view' ), $admin );
	$r->add( 'POST', '/onboarding/{id}/advance/{step}', array( Onboarding_Controller::class, 'advance' ), $admin );
	$r->add( 'GET', '/onboarding/{id}/summary', array( Onboarding_Controller::class, 'summary_view' ), $admin );
	$r->add( 'POST', '/onboarding/{id}/finish', array( Onboarding_Controller::class, 'finish' ), $admin );
	$r->add( 'POST', '/businesses/{id}/perception/external', array( Onboarding_Controller::class, 'reanalyze' ), $admin );
	$r->add( 'GET', '/businesses/{id}/perception', array( Perception_Controller::class, 'index' ) );
	$r->add( 'POST', '/businesses/{id}/perception/internal/{site_id}', array( Perception_Controller::class, 'internal' ) );

	// Business sub-resources (Stage 2). Authorization: Business_Scoped_Controller::business().
	foreach ( array( 'contacts', 'offerings', 'rules' ) as $collection ) {
		$r->add( 'GET', '/businesses/{id}/' . $collection, array( Business_Children_Controller::class, 'index' ) );
		$r->add( 'POST', '/businesses/{id}/' . $collection, array( Business_Children_Controller::class, 'store' ) );
		$r->add( 'POST', '/businesses/{id}/' . $collection . '/{item_id}', array( Business_Children_Controller::class, 'update' ) );
		$r->add( 'POST', '/businesses/{id}/' . $collection . '/{item_id}/delete', array( Business_Children_Controller::class, 'destroy' ) );
		$r->add( 'POST', '/businesses/{id}/' . $collection . '/{item_id}/toggle', array( Business_Children_Controller::class, 'toggle' ) );
	}
	$r->add( 'GET', '/businesses/{id}/usp', array( Business_Usp_Controller::class, 'index' ) );
	$r->add( 'POST', '/businesses/{id}/usp', array( Business_Usp_Controller::class, 'save' ) );
	$r->add( 'GET', '/businesses/{id}/ai', array( Business_Ai_Controller::class, 'index' ) );
	$r->add( 'POST', '/businesses/{id}/ai', array( Business_Ai_Controller::class, 'update' ) );
	$r->add( 'POST', '/businesses/{id}/ai/key', array( Business_Ai_Controller::class, 'save_key' ) );
	$r->add( 'POST', '/businesses/{id}/repuvio', array( Business_Ai_Controller::class, 'save_repuvio' ) );
	$r->add( 'GET', '/businesses/{id}/sites', array( Sites_Controller::class, 'business_index' ) );
	$r->add( 'POST', '/businesses/{id}/sites', array( Sites_Controller::class, 'store' ) );

	// Sites. Authorization: Gate::authorize_site().
	$r->add( 'GET', '/sites', array( Sites_Controller::class, 'index_all' ) );
	$r->add( 'GET', '/sites/{id}', array( Sites_Controller::class, 'show' ) );
	$r->add( 'POST', '/sites/{id}', array( Sites_Controller::class, 'update' ) );
	$r->add( 'POST', '/sites/{id}/rotate', array( Sites_Controller::class, 'rotate_secret' ) );
	$r->add( 'POST', '/sites/{id}/revoke', array( Sites_Controller::class, 'revoke' ) );
	$r->add( 'POST', '/sites/{id}/integrations/{provider}', array( Sites_Controller::class, 'save_integration' ) );

	// Deterministic strategy engine for the ACTIVE business (Stage 6).
	$r->add( 'GET', '/seo-audit', array( Strategy_Controller::class, 'seo_audit' ) );
	$r->add( 'GET', '/keywords', array( Strategy_Controller::class, 'keywords' ) );
	$r->add( 'POST', '/keywords', array( Strategy_Controller::class, 'keyword_add' ) );
	$r->add( 'POST', '/keywords/{id}/status', array( Strategy_Controller::class, 'keyword_status' ) );
	$r->add( 'POST', '/insights/{id}/dismiss', array( Strategy_Controller::class, 'insight_dismiss' ) );
	$r->add( 'GET', '/ads', array( Strategy_Controller::class, 'ads' ) );
	$r->add( 'GET', '/competitors', array( Strategy_Controller::class, 'competitors' ) );
	$r->add( 'POST', '/competitors', array( Strategy_Controller::class, 'competitor_add' ) );
	$r->add( 'POST', '/competitors/discover', array( Strategy_Controller::class, 'competitor_discover' ) );
	$r->add( 'POST', '/competitors/scan', array( Strategy_Controller::class, 'competitor_scan' ) );
	$r->add( 'POST', '/competitors/{id}/toggle', array( Strategy_Controller::class, 'competitor_toggle' ) );
	$r->add( 'GET', '/competitors/compare', array( Strategy_Controller::class, 'compare' ) );
	$r->add( 'POST', '/engine/run', array( Strategy_Controller::class, 'engine_run' ) );

	// AI on demand + Action Cards (Stage 7) — active business only.
	$r->add( 'GET', '/analyses', array( Analyses_Controller::class, 'index' ) );
	$r->add( 'GET', '/analyses/new', array( Analyses_Controller::class, 'create' ) );
	$r->add( 'POST', '/analyses', array( Analyses_Controller::class, 'store' ) );
	$r->add( 'GET', '/analyses/{id}', array( Analyses_Controller::class, 'show' ) );
	$r->add( 'GET', '/actions', array( Actions_Controller::class, 'index' ) );
	$r->add( 'GET', '/actions/{id}', array( Actions_Controller::class, 'show' ) );
	$r->add( 'POST', '/actions/{id}', array( Actions_Controller::class, 'update' ) );
	$r->add( 'POST', '/actions/{id}/apply', array( Actions_Controller::class, 'apply' ) );
	$r->add( 'POST', '/actions/{id}/manual', array( Actions_Controller::class, 'manual' ) );
	$r->add( 'POST', '/actions/{id}/reject', array( Actions_Controller::class, 'reject' ) );
	$r->add( 'POST', '/actions/{id}/rollback', array( Actions_Controller::class, 'rollback' ) );
	$r->add( 'POST', '/actions/{id}/generate', array( Actions_Controller::class, 'generate' ) );
	$r->add( 'POST', '/actions/{id}/images', array( Actions_Controller::class, 'image_add' ) );
	$r->add( 'POST', '/actions/{id}/images/{image_id}', array( Actions_Controller::class, 'image_upload' ) );
	$r->add( 'GET', '/uploads/{id}', array( Actions_Controller::class, 'upload_file' ) );

	// Google integrations (Stage 5). Accounts are super admin only.
	$r->add( 'GET', '/google', array( Google_Controller::class, 'index' ) );
	$r->add( 'POST', '/google/connect', array( Google_Controller::class, 'connect' ), $admin );
	$r->add( 'GET', '/oauth/google/callback', array( Google_Controller::class, 'callback' ), $admin );
	$r->add( 'GET', '/google/accounts/{id}', array( Google_Controller::class, 'account' ), $admin );
	$r->add( 'POST', '/google/accounts/{id}/discover', array( Google_Controller::class, 'discover' ), $admin );
	$r->add( 'POST', '/google/accounts/{id}/disconnect', array( Google_Controller::class, 'disconnect' ), $admin );
	$r->add( 'POST', '/sites/{id}/integrations/{provider}/sync', array( Google_Controller::class, 'sync_now' ) );
	$r->add( 'GET', '/sites/{id}/google', array( Google_Controller::class, 'site_data' ) );

	// Connector: plugin download, remote operations, mirrored content (Stage 4).
	$r->add( 'POST', '/sites/{id}/plugin', array( Site_Connector_Controller::class, 'download' ) );
	$r->add( 'POST', '/sites/{id}/ping', array( Site_Connector_Controller::class, 'ping' ) );
	$r->add( 'POST', '/sites/{id}/resync', array( Site_Connector_Controller::class, 'resync' ) );
	$r->add( 'GET', '/sites/{id}/content', array( Site_Connector_Controller::class, 'content' ) );
	$r->add( 'GET', '/sites/{id}/content/{content_id}', array( Site_Connector_Controller::class, 'content_show' ) );
	$r->add( 'POST', '/sites/{id}/content/{content_id}', array( Site_Connector_Controller::class, 'content_update' ) );
	$r->add( 'GET', '/sites/{id}/operations', array( Site_Connector_Controller::class, 'operations' ) );
	$r->add( 'POST', '/sites/{id}/operations/{op_id}/rollback', array( Site_Connector_Controller::class, 'rollback' ) );

	// Profile.
	$r->add( 'GET', '/profile', array( Profile_Controller::class, 'index' ) );
	$r->add( 'POST', '/profile', array( Profile_Controller::class, 'update' ) );
	$r->add( 'POST', '/profile/password', array( Profile_Controller::class, 'update_password' ) );

	// Administration (super admin only).
	$r->add( 'GET', '/users', array( Users_Controller::class, 'index' ), $admin );
	$r->add( 'GET', '/users/new', array( Users_Controller::class, 'create' ), $admin );
	$r->add( 'POST', '/users', array( Users_Controller::class, 'store' ), $admin );
	$r->add( 'GET', '/users/{id}/edit', array( Users_Controller::class, 'edit' ), $admin );
	$r->add( 'POST', '/users/{id}', array( Users_Controller::class, 'update' ), $admin );
	$r->add( 'GET', '/audit', array( Audit_Controller::class, 'index' ), $admin );
	$r->add( 'GET', '/settings', array( Settings_Controller::class, 'index' ), $admin );
	$r->add( 'POST', '/settings/{section}', array( Settings_Controller::class, 'update' ), $admin );
	$r->add( 'POST', '/settings/credentials/{provider}', array( Settings_Controller::class, 'save_credential' ), $admin );
	$r->add( 'POST', '/jobs/run-batch', array( Cron_Controller::class, 'run_batch' ), $admin );
	$r->add( 'POST', '/settings/test/{provider}', array( Settings_Controller::class, 'test_connection' ), $admin );

	// Backup to Google Drive (super admin).
	$r->add( 'GET', '/backup', array( Backup_Controller::class, 'index' ), $admin );
	$r->add( 'GET', '/backup/status', array( Backup_Controller::class, 'status' ), $admin );
	$r->add( 'POST', '/backup/settings', array( Backup_Controller::class, 'save' ), $admin );
	$r->add( 'POST', '/backup/drive/connect', array( Google_Controller::class, 'connect_drive' ), $admin );
	$r->add( 'POST', '/backup/drive/disconnect', array( Backup_Controller::class, 'disconnect' ), $admin );
	$r->add( 'POST', '/backup/drive/folder', array( Backup_Controller::class, 'folder' ), $admin );
	$r->add( 'POST', '/backup/run', array( Backup_Controller::class, 'run' ), $admin );
	$r->add( 'POST', '/backup/download', array( Backup_Controller::class, 'download' ), $admin );
	$r->add( 'POST', '/backup/{id}/delete', array( Backup_Controller::class, 'destroy' ), $admin );
};
