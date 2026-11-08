<?php
/**
 * OptiVio — HTTP exception (converted to an error response by the App).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Core;

use RuntimeException;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Exception carrying an HTTP status code.
 */
class Http_Exception extends RuntimeException {

	/**
	 * HTTP status.
	 *
	 * @var int
	 */
	private int $status;

	/**
	 * Constructor.
	 *
	 * @param int    $status  HTTP status code.
	 * @param string $message Public message (safe to display).
	 */
	public function __construct( int $status, string $message = '' ) {
		parent::__construct( $message, $status );
		$this->status = $status;
	}

	/**
	 * Returns the HTTP status.
	 *
	 * @return int
	 */
	public function get_status(): int {
		return $this->status;
	}

	/**
	 * 404 helper — also used for denied resources to avoid ID enumeration.
	 *
	 * @return Http_Exception
	 */
	public static function not_found(): Http_Exception {
		return new self( 404, 'Pagina sau resursa nu a fost găsită.' );
	}

	/**
	 * 403 helper.
	 *
	 * @return Http_Exception
	 */
	public static function forbidden(): Http_Exception {
		return new self( 403, 'Nu aveți permisiunea de a accesa această resursă.' );
	}
}
