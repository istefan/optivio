<?php
/**
 * OptiVio — Builds the per-site OptiVio Connector zip (connection.php embedded).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Services;

use OptiVio\Core\Config;
use OptiVio\Core\Zip_Writer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Plugin packager.
 */
final class Plugin_Packager {

	public const SLUG = 'optivio-connector';

	/**
	 * Builds the zip bytes for a site.
	 *
	 * @param string $key_id Public key id.
	 * @param string $secret Plain secret (from Sites_Repository::find_by_connector_key()).
	 * @return string
	 * @throws RuntimeException When the plugin source is missing.
	 */
	public static function build( string $key_id, string $secret ): string {
		$source = OPTIVIO_ROOT . '/plugin/' . self::SLUG;
		if ( ! is_dir( $source ) ) {
			throw new RuntimeException( 'Plugin source missing.' );
		}

		$zip      = new Zip_Writer();
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $source ) + 1 ) );
			if ( 'connection.php' === $relative || str_starts_with( basename( $relative ), '.' ) ) {
				continue;
			}
			$zip->add( self::SLUG . '/' . $relative, (string) file_get_contents( $file->getPathname() ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
		$zip->add( self::SLUG . '/connection.php', self::connection_file( $key_id, $secret ) );
		return $zip->contents();
	}

	/**
	 * Generated connection.php.
	 *
	 * @param string $key_id Key id.
	 * @param string $secret Secret.
	 * @return string
	 */
	private static function connection_file( string $key_id, string $secret ): string {
		$values = array(
			'api_base' => rtrim( (string) Config::get( 'app_url', '' ), '/' ),
			'key_id'   => $key_id,
			'secret'   => $secret,
		);
		return "<?php\n/**\n * OptiVio Connector — generated connection settings. Keep this file private.\n * Generated: " . gmdate( 'c' ) . "\n *\n * @package OptiVio_Connector\n */\n\ndefined( 'ABSPATH' ) || exit;\n\nreturn " . var_export( $values, true ) . ";\n"; // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
	}
}
