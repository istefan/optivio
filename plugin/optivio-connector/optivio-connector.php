<?php
/**
 * Plugin Name:       OptiVio Connector
 * Description:       Conectează site-ul la OptiVio: atribuirea sursei de trafic, click-uri pe telefon, formulare Gravity Forms cu filtru anti-spam, oglindirea conținutului și a setărilor Yoast SEO, aplicarea modificărilor aprobate cu snapshot și rollback.
 * Version:           1.1.0
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            Stefan Iftimie, founder Ahoi Digital SRL
 * Author URI:        https://www.ahoi.ro/
 * License:           Proprietary
 * Text Domain:       optivio-connector
 * Domain Path:       /languages
 *
 * @package   OptiVio_Connector
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

defined( 'ABSPATH' ) || exit;

define( 'OVC_VERSION', '1.1.0' );
define( 'OVC_DB_VERSION', '1' );
define( 'OVC_FILE', __FILE__ );
define( 'OVC_DIR', plugin_dir_path( __FILE__ ) );
define( 'OVC_URL', plugin_dir_url( __FILE__ ) );

require_once OVC_DIR . 'includes/class-ovc-settings.php';
require_once OVC_DIR . 'includes/class-ovc-signer.php';
require_once OVC_DIR . 'includes/class-ovc-client.php';
require_once OVC_DIR . 'includes/class-ovc-queue.php';
require_once OVC_DIR . 'includes/class-ovc-attribution.php';
require_once OVC_DIR . 'includes/class-ovc-tracking.php';
require_once OVC_DIR . 'includes/class-ovc-forms.php';
require_once OVC_DIR . 'includes/class-ovc-content.php';
require_once OVC_DIR . 'includes/class-ovc-snapshots.php';
require_once OVC_DIR . 'includes/class-ovc-remote.php';
require_once OVC_DIR . 'includes/class-ovc-heartbeat.php';
require_once OVC_DIR . 'includes/class-ovc-admin.php';
require_once OVC_DIR . 'includes/class-ovc-plugin.php';

register_activation_hook( __FILE__, array( 'OVC_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'OVC_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'OVC_Plugin', 'boot' ) );
