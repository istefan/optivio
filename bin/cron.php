<?php
/**
 * OptiVio — Cron entry point (run every 1-5 minutes from cPanel).
 *
 * Usage: php bin/cron.php
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

require __DIR__ . '/cli-bootstrap.php';

$ov_result = OptiVio\Services\Cron_Runner::run( 'cli' );

fwrite( // phpcs:ignore
	STDOUT,
	sprintf( "[%s] status=%s processed=%d failed=%d pending=%d\n", gmdate( 'c' ), $ov_result['status'], $ov_result['processed'], $ov_result['failed'], $ov_result['pending'] )
);
