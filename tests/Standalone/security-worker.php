<?php

// phpcs:disable WordPress.Security.EscapeOutput -- Synthetic CLI fixture results.
define( 'APPNA_SECURITY_WORKER', true );
require __DIR__ . '/security-wordpress-bootstrap.php';

use Crafium\AppNatively\App\Support\Auth;
use Crafium\AppNatively\App\Support\CheckoutHandoff;

try {
    $operation = $argv[2] ?? '';
    $user      = (int) ( $argv[3] ?? 0 );
    if ( $operation === 'issue' ) {
        $result = Auth::issue( $user );
    } elseif ( $operation === 'revoke' ) {
        Auth::revoke( $argv[3] );
        $result = true;
    } elseif ( $operation === 'redeem' ) {
        $cookies = 0;
        $result  = CheckoutHandoff::redeem(
            $argv[3], static function () use ( &$cookies ): void { ++$cookies;
            }
        );
        $result  = [
            $result,
            $cookies
        ];
    } else {
        throw new RuntimeException( 'Unknown fixture operation.' );
    }
    echo json_encode( ['result' => $result] );
} catch ( Throwable $error ) {
    echo json_encode( ['error' => $error->getCode()] );
}
