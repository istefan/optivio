<?php
/**
 * OptiVio — Front controller. The only PHP file exposed to the web.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

define( 'OPTIVIO_ROOT', dirname( __DIR__ ) );

require OPTIVIO_ROOT . '/app/bootstrap.php';

OptiVio\Core\App::run_http();
