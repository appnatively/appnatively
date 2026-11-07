<?php

// phpcs:disable WordPress.Security.EscapeOutput -- CLI-only test diagnostics and protocol fixtures are not HTML.
// phpcs:disable WordPress.DB.RestrictedClasses -- Synthetic database adapters test without loading site configuration.

/** Child of the isolated PHPUnit concurrency test; credentials arrive only through stdin. */
if ( PHP_SAPI !== 'cli' ) {
    exit;
}
define( 'ABSPATH', '/unused/' );
$config = json_decode( stream_get_contents( STDIN ), true );
if ( ! is_array( $config ) || strpos( $config['database'] ?? '', 'test' ) === false || ! preg_match( '/^[a-zA-Z0-9_]+$/', $config['prefix'] ?? '' ) ) {
    exit( 2 );
}

function update_option( ...$args ) {
}

class SequenceConnection
{
    public $prefix;

    private $pdo;

    public function __construct( array $config ) {
        $this->prefix = $config['prefix'];
        $host         = $config['host'];
        if ( strpos( $host, ':/' ) !== false ) {
            $dsn = 'mysql:unix_socket=' . substr( $host, strpos( $host, ':' ) + 1 );
        } else {
            $parts = explode( ':', $host, 2 );
            $dsn   = 'mysql:host=' . $parts[0] . ( isset( $parts[1] ) ? ';port=' . (int) $parts[1] : '' );
        }
        $this->pdo = new PDO(
            $dsn . ';dbname=' . $config['database'], $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]
        );
    }

    public function prepare( $sql, ...$values ) {
        $index = 0;
        return preg_replace_callback(
            '/%[sdi]/', function ( $match ) use ( &$index, $values ) {
                $value = $values[$index++];
                return $match[0] === '%d' ? (string) (int) $value : ( $match[0] === '%i' ? '`' . str_replace( '`', '``', $value ) . '`' : $this->pdo->quote( (string) $value ) );
            }, $sql
        );
    }

    public function query( $sql ) {
        return $this->pdo->exec( $sql );
    }

    public function get_var( $sql ) {
        return $this->pdo->query( $sql )->fetchColumn();
    }
}
try {
    $wpdb = new SequenceConnection( $config );
    require dirname( __DIR__, 2 ) . '/app/Support/NotificationEvents.php';
    $versions = [];
    for ( $index = 0; $index < 25; $index++ ) {
        $versions[] = \Crafium\AppNatively\App\Support\NotificationEvents::next_version( 'race', $config['entityId'], 500 );
    }
    echo json_encode( $versions );
} catch ( Throwable $error ) {
    fwrite( STDERR, "Isolated sequence worker failed.\n" );
    exit( 1 );
}
