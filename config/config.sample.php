<?php
/**
 * OptiVio — Configuration template.
 *
 * Copy this file to config/config.php and fill in the values.
 * Generate keys with: php bin/generate-key.php
 * NEVER commit config/config.php.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

defined( 'OPTIVIO_ROOT' ) || exit;

return array(
	'app_env'          => 'production', // production | development.
	'app_url'          => 'https://optivio.ahoi.ro', // No trailing slash. May contain a sub-path (e.g. http://localhost/optivio).
	'app_timezone'     => 'Europe/Bucharest',
	'force_https'      => true,

	'db'               => array(
		'host'     => 'localhost',
		'port'     => 3306,
		'name'     => 'optivio',
		'user'     => 'optivio',
		'password' => '',
		'charset'  => 'utf8mb4',
	),

	// 32 random bytes, base64 encoded. Losing this key = losing all encrypted secrets.
	'master_key'       => '',
	// Previous master keys (key id => base64 key) kept only for decrypting during rotation.
	'previous_keys'    => array(),
	'master_key_id'    => 1,

	// One-time token required by the web installer (/setup). Only works while no user exists.
	'install_token'    => '',
	// Token for HTTP cron trigger (/cron/run?token=...). Leave empty to disable HTTP cron.
	'cron_token'       => '',

	// DEVELOPMENT ONLY: serve Google API calls from local fixtures (ignored when app_env = production).
	'google_mock'      => false,

	// DEVELOPMENT ONLY: answer OpenRouter calls with local fixtures (ignored when app_env = production).
	'ai_mock'          => false,

	// Trusted reverse proxies (exact IPs). Only then X-Forwarded-For is honoured.
	'trusted_proxies'  => array(),

	'session'          => array(
		'name'             => 'OVSESSID',
		'idle_timeout'     => 1800,  // Seconds of inactivity before logout (overridable in settings).
		'absolute_timeout' => 43200, // Max session lifetime in seconds.
	),
);
