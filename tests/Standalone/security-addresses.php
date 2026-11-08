<?php

// phpcs:disable WordPress.Security.EscapeOutput -- CLI regression diagnostics.
define( 'ABSPATH', '/unused/' );
require dirname( __DIR__, 2 ) . '/app/Support/ClientAddress.php';
use Crafium\AppNatively\App\Support\ClientAddress as Address;

$proxies = [];

function apply_filters( $hook, $value ) {
    return $GLOBALS['proxies'];
}

$checks = 0;

function check( $ok, $message ): void {
    if ( ! $ok ) {
        throw new RuntimeException( $message );
    }
    ++$GLOBALS['checks'];
}

$cases = [
    [
        [
            'REMOTE_ADDR'    => '198.51.100.8',
            'HTTP_CLIENT_IP' => '203.0.113.1'
        ],
        [],
        '198.51.100.8'
    ],
    [
        [
            'REMOTE_ADDR'          => '198.51.100.8',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.1'
        ],
        [],
        '198.51.100.8'
    ],
    [
        [
            'REMOTE_ADDR'           => '198.51.100.8',
            'HTTP_CF_CONNECTING_IP' => '203.0.113.1'
        ],
        [],
        '198.51.100.8'
    ],
    [
        [
            'REMOTE_ADDR'          => '10.0.0.2',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.1, 198.51.100.8, 10.0.0.1'
        ],
        ['10.0.0.0/24'],
        '198.51.100.8'
    ],
    [
        [
            'REMOTE_ADDR'          => '10.0.0.2',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.1, 10.0.0.1'
        ],
        ['10.0.0.0/24'],
        '203.0.113.1'
    ],
    [
        [
            'REMOTE_ADDR'          => '10.0.1.2',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.1'
        ],
        ['10.0.0.0/24'],
        '10.0.1.2'
    ],
    [
        [
            'REMOTE_ADDR'          => '10.0.0.2',
            'HTTP_X_FORWARDED_FOR' => 'bad, 198.51.100.8'
        ],
        ['10.0.0.0/24'],
        '10.0.0.2'
    ],
    [
        [
            'REMOTE_ADDR'          => '10.0.0.2',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.8:123'
        ],
        ['10.0.0.0/24'],
        '10.0.0.2'
    ],
    [
        [
            'REMOTE_ADDR'          => '10.0.0.2',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.8'
        ],
        [
            'bad/2',
            '10.0.0.0/33'
        ],
        '10.0.0.2'
    ],
    [
        ['REMOTE_ADDR' => '2001:0DB8:0000:0000:0000:0000:0000:0001'],
        [],
        '2001:db8::1'
    ],
    [
        ['REMOTE_ADDR' => '::ffff:198.51.100.8'],
        [],
        '198.51.100.8'
    ],
    [
        [
            'REMOTE_ADDR'          => '2001:db8:1::2',
            'HTTP_X_FORWARDED_FOR' => '2001:db8:2::1, 2001:db8:1::1'
        ],
        ['2001:db8:1::/48'],
        '2001:db8:2::1'
    ],
    [
        [
            'REMOTE_ADDR'          => '2001:db8:1::2',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.8'
        ],
        ['2001:db8:1::2/128'],
        '198.51.100.8'
    ],
    [
        [],
        [],
        Address::UNKNOWN
    ],
    [
        [
            'REMOTE_ADDR'    => 'bad',
            'HTTP_CLIENT_IP' => '198.51.100.8'
        ],
        [],
        Address::UNKNOWN
    ],
    [
        ['REMOTE_ADDR' => ['198.51.100.8']],
        [],
        Address::UNKNOWN
    ],
];
foreach ( $cases as $i => [$server, $allowlist, $expected] ) {
    $_SERVER = $server;
    $proxies = $allowlist;
    check( Address::resolve() === $expected, 'Address resolution case ' . $i );
}
$_SERVER  = [
    'REMOTE_ADDR'          => '10.0.0.2',
    'HTTP_CLIENT_IP'       => 'spoof',
    'HTTP_X_FORWARDED_FOR' => '198.51.100.8'
];
$proxies  = ['10.0.0.0/24'];
$original = $_SERVER;
try {
    Address::native(
        static function ( $ip ): void {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Assert the synthetic native integration input verbatim.
            check( $ip === '198.51.100.8' && ( $_SERVER['REMOTE_ADDR'] ?? '' ) === $ip && ! isset( $_SERVER['HTTP_CLIENT_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'] ), 'Native integration receives only the resolved address' );
            throw new RuntimeException( 'expected fixture exception' );
        }
    );
} catch ( RuntimeException $error ) {
    check( $_SERVER === $original, 'Native exception restores original server state' );
}
echo "PASS: {$checks} address security checks\n";
