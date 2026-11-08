<?php

// phpcs:disable WordPress.Security.EscapeOutput -- CLI-only regression diagnostics.
require __DIR__ . '/notification-fixture.php';

use Crafium\AppNatively\Database\Setup;
use Crafium\AppNatively\App\Support\NotificationEvents;
use Crafium\AppNatively\WpMVC\Database\Schema\Blueprint;

require $base . '/vendor-src/wpmvc/database/src/Schema/Blueprint.php';

$checks = 0;

function check( $condition, $message ) {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
    ++$GLOBALS['checks'];
}

// Observe framework execution without running MySQL DDL in this SQLite fixture.
// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- Match the WordPress API.
function dbDelta( $sql ) {
    $GLOBALS['schema_calls'][] = [
        $sql,
        debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 1 )[0]['file']
    ];
    return [];
}

$original = new \WpMVC\Database\Schema\Blueprint( 'legacy_table', 'DEFAULT CHARACTER SET utf8mb4' );
$enhanced = new Blueprint( 'legacy_table', 'DEFAULT CHARACTER SET utf8mb4' );
foreach ( [
    $original,
    $enhanced
] as $table ) {
    $table->big_increments( 'id' );
    $table->long_text( 'description' );
    $table->long_text( 'notes' )->nullable();
    $table->string( 'name', 100 );
    $table->unsigned_big_integer( 'storage_version' )->default( 1 );
    $table->index( 'name' );
}
check( $enhanced->to_sql() === $original->to_sql(), 'Existing Blueprint calls retain identical SQL' );
$required = new Blueprint( 'required_table', '' );
$required->engine( 'InnoDB' )->long_text( 'payload', false );
check( strpos( $required->to_sql(), 'payload LONGTEXT NOT NULL' ) !== false && strpos( $required->to_sql(), 'ENGINE=InnoDB;' ) !== false, 'Explicit constraints are emitted by Blueprint' );
$required->nullable();
check( strpos( $required->to_sql(), 'payload LONGTEXT NULL' ) !== false, 'The existing nullable modifier still works' );
try {
    $required->engine( 'InnoDB; DROP TABLE example' );
    throw new LogicException( 'Expected invalid engine rejection.' );
} catch ( InvalidArgumentException $error ) {
    check( true, 'Engine names cannot inject additional SQL' );
}

$patcher       = require $base . '/dev-tools/scoper/patches/database-blueprint.php';
$upstream      = $base . '/vendor-src/wpmvc/database/src/Schema/Blueprint.php';
$scoped_before = str_replace( 'namespace WpMVC\\Database\\Schema;', 'namespace Crafium\\AppNatively\\WpMVC\\Database\\Schema;', file_get_contents( $upstream ) );
check( $patcher( '/other/Blueprint.php', 'Crafium\\AppNatively', 'unrelated' ) === 'unrelated', 'The patcher ignores unrelated blueprints' );
try {
    $patcher( $upstream, 'Crafium\\AppNatively', $scoped_before );
    throw new LogicException( 'Expected changed formatting rejection.' );
} catch ( RuntimeException $error ) {
    check( true, 'Unexpected scoped source patterns fail the build' );
}
$changed_source = sys_get_temp_dir() . '/appna-schema-patch-' . bin2hex( random_bytes( 6 ) );
$changed_dir    = $changed_source . '/vendor-src/wpmvc/database/src/Schema';
mkdir( $changed_dir, 0777, true );
file_put_contents( $changed_dir . '/Blueprint.php', file_get_contents( $upstream ) . "\n" );
try {
    $patcher( $changed_dir . '/Blueprint.php', 'Crafium\\AppNatively', $scoped_before );
    throw new LogicException( 'Expected changed dependency rejection.' );
} catch ( RuntimeException $error ) {
    check( strpos( $error->getMessage(), 'Unexpected WpMVC Blueprint source' ) !== false, 'Changed dependency source fails the build' );
} finally {
    unlink( $changed_dir . '/Blueprint.php' );
    for ( $directory = $changed_dir; $directory !== dirname( $changed_source ); $directory = dirname( $directory ) ) {
        rmdir( $directory );
    }
}

$wpdb = new class extends NotificationTestDatabase {
    public function suppress_errors( $value = true ) {
        return parent::suppress_errors( $value );
    }

    public function get_results( $sql, $mode = null ) {
        $this->last_error = '';
        if ( $this->fail && strpos( $sql, $this->fail ) !== false ) {
            $this->last_error = 'synthetic database failure';
            return [];
        }
        return parent::get_results( $sql, $mode );
    }
};
foreach ( [
    'wp_',
    'custom_',
    'network_7_'
] as $prefix ) {
    $wpdb->prefix = $prefix;
    $wpdb->db->exec( "CREATE TABLE {$prefix}craf_appna_event_bindings(source_id TEXT); CREATE TABLE {$prefix}craf_appna_event_sequences(entity_type TEXT);" );
    delete_option( 'craf_appna_events_schema' );
    $schema_calls = [];
    Setup::install_notifications();
    check(
        count( $schema_calls ) === 2 && count(
            array_filter(
                $schema_calls, function ( $call ) { return substr( $call[1], -18 ) === '/Schema/Schema.php';
                }
            )
        ) === 2, 'Only Schema::create invokes dbDelta, once per table'
    );
    check( strpos( $schema_calls[0][0], $prefix . 'craf_appna_event_bindings' ) !== false && strpos( $schema_calls[0][0], 'payload LONGTEXT NOT NULL' ) !== false && strpos( $schema_calls[1][0], 'ENGINE=InnoDB;' ) !== false, 'Setup preserves prefixes and constraints' );
    check( (int) get_option( 'craf_appna_events_schema' ) === NotificationEvents::SCHEMA_VERSION, 'Successful setup records the unchanged schema version' );
    $schema_calls = [];
    Setup::install_notifications();
    check( ! $schema_calls, 'Current schema version prevents repeated DDL' );
    delete_option( 'craf_appna_events_schema' );
    $wpdb->fail = 'craf_appna_event_bindings';
    Setup::install_notifications();
    check( get_option( 'craf_appna_events_schema', false ) === false && get_option( 'craf_appna_event_last_error' )['code'] === 'notification_storage_unavailable', 'Unavailable storage records an error and is not marked installed' );
    $wpdb->fail = '';
}
echo "PASS: {$checks} schema setup and build-patch checks\n";
