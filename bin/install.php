<?php
/**
 * OptiVio — CLI installer: creates a Super Admin.
 *
 * Usage: php bin/install.php --email=admin@example.com --name="Admin"
 * The password is read interactively (or from OPTIVIO_ADMIN_PASSWORD env var for automation).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

require __DIR__ . '/cli-bootstrap.php';

use OptiVio\Core\Crypto;
use OptiVio\Repositories\Users_Repository;
use OptiVio\Security\Audit_Log;
use OptiVio\Security\Password_Policy;

$ov_opts = ov_cli_options( $argv );
$ov_mail = sanitize_email( $ov_opts['email'] ?? '' );
$ov_name = sanitize_text_field( $ov_opts['name'] ?? 'Super Admin' );

if ( ! Crypto::is_configured() ) {
	fwrite( STDERR, "master_key invalid în config.php. Rulați php bin/generate-key.php.\n" ); // phpcs:ignore
	exit( 1 );
}
if ( '' === $ov_mail ) {
	fwrite( STDERR, "Utilizare: php bin/install.php --email=admin@exemplu.ro --name=\"Nume\"\n" ); // phpcs:ignore
	exit( 1 );
}
if ( Users_Repository::email_exists( $ov_mail ) ) {
	fwrite( STDERR, "Există deja un utilizator cu acest email.\n" ); // phpcs:ignore
	exit( 1 );
}

$ov_pass = (string) getenv( 'OPTIVIO_ADMIN_PASSWORD' );
if ( '' === $ov_pass ) {
	$ov_pass    = ov_cli_secret( 'Parolă: ' );
	$ov_confirm = ov_cli_secret( 'Confirmare parolă: ' );
	if ( ! hash_equals( $ov_pass, $ov_confirm ) ) {
		fwrite( STDERR, "Parolele nu coincid.\n" ); // phpcs:ignore
		exit( 1 );
	}
}

$ov_error = Password_Policy::validate( $ov_pass, $ov_mail );
if ( null !== $ov_error ) {
	fwrite( STDERR, $ov_error . "\n" ); // phpcs:ignore
	exit( 1 );
}

$ov_id = Users_Repository::create(
	array(
		'email'         => $ov_mail,
		'display_name'  => '' !== $ov_name ? $ov_name : 'Super Admin',
		'role'          => 'super_admin',
		'password_hash' => Password_Policy::hash( $ov_pass ),
	)
);
Audit_Log::record( 'setup.super_admin', 'user', $ov_id, array( 'via' => 'cli' ), null, $ov_id );

fwrite( STDOUT, "Super Admin creat (#{$ov_id}).\n" ); // phpcs:ignore
