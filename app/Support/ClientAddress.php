<?php

namespace Crafium\AppNatively\App\Support;

defined( 'ABSPATH' ) || exit;

/** Forwarded addresses are evidence only when the immediate peer is trusted. */
class ClientAddress {
    const UNKNOWN = '0.0.0.0';

    public static function resolve(): string {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- canonical() strictly validates the raw IP; no HTML or slash normalization.
        $peer = self::canonical( $_SERVER['REMOTE_ADDR'] ?? null );
        if ( $peer === null ) {
            return self::UNKNOWN;
        }
        $proxies = (array) apply_filters( 'craf_appna_trusted_proxy_cidrs', [] );
        if ( ! self::trusted( $peer, $proxies ) ) {
            return $peer;
        }
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Each bounded chain element is strictly validated below.
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ( ! is_string( $forwarded ) || $forwarded === '' || strlen( $forwarded ) > 8192 ) {
            return $peer;
        }
        $chain = explode( ',', $forwarded );
        if ( count( $chain ) > 64 ) {
            return $peer;
        }
        foreach ( $chain as &$address ) {
            $address = self::canonical( trim( $address ) );
            if ( $address === null ) {
                return $peer;
            }
        }
        unset( $address );
        for ( $i = count( $chain ) - 1; $i >= 0 && self::trusted( $peer, $proxies ); --$i ) {
            $peer = $chain[$i];
        }
        return $peer;
    }

    public static function canonical( $address ): ?string {
        if ( ! is_string( $address ) || ! filter_var( $address, FILTER_VALIDATE_IP ) ) {
            return null;
        }
        $packed = inet_pton( $address );
        if ( strlen( $packed ) === 16 && substr( $packed, 0, 12 ) === str_repeat( "\0", 10 ) . "\xff\xff" ) {
            $packed = substr( $packed, 12 );
        }
        return inet_ntop( $packed );
    }

    private static function trusted( string $address, array $proxies ): bool {
        $packed = inet_pton( $address );
        foreach ( $proxies as $cidr ) {
            if ( ! is_string( $cidr ) ) {
                continue;
            }
            $parts   = explode( '/', $cidr );
            $network = self::canonical( $parts[0] );
            if ( $network === null || count( $parts ) > 2 ) {
                continue;
            }
            $network = inet_pton( $network );
            $width   = strlen( $network ) * 8;
            $bits    = $parts[1] ?? (string) $width;
            if ( ! ctype_digit( $bits ) || (int) $bits > $width || strlen( $packed ) !== strlen( $network ) ) {
                continue;
            }
            $bits  = (int) $bits;
            $bytes = intdiv( $bits, 8 );
            $rest  = $bits % 8;
            if ( substr( $packed, 0, $bytes ) === substr( $network, 0, $bytes ) &&
                ( ! $rest || ( ord( $packed[$bytes] ) & ( 255 << ( 8 - $rest ) ) ) === ( ord( $network[$bytes] ) & ( 255 << ( 8 - $rest ) ) ) ) ) {
                return true;
            }
        }
        return false;
    }

    /** Native integrations must not re-read the original spoofable headers. */
    public static function native( callable $operation ) {
        $address                = self::resolve();
        $server                 = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = $address;
        foreach ( ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP', 'HTTP_FORWARDED'] as $header ) {
            unset( $_SERVER[$header] );
        }
        try {
            return $operation( $address );
        } finally {
            $_SERVER = $server;
        }
    }
}
