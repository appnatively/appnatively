<?php

// phpcs:disable WordPress.Security.EscapeOutput -- CLI-only regression diagnostics.
require __DIR__ . '/notification-fixture.php';

use Crafium\AppNatively\App\Models\DatabaseRow;
use Crafium\AppNatively\Database\Setup;
use Crafium\AppNatively\WpMVC\Database\Schema\Schema;
use Crafium\AppNatively\App\Integrations\Directory\Concerns\ListingIntegrationHelpers;
use Crafium\AppNatively\App\Integrations\Directory\Catalog\ListingCatalog;

$checks = 0;

function check( $condition, $message ) {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
    ++$GLOBALS['checks'];
}

$wpdb->prefix      = 'site_2_';
$wpdb->base_prefix = 'network_';
$wpdb->db->exec( 'CREATE TABLE network_users(ID INTEGER PRIMARY KEY,display_name TEXT); INSERT INTO network_users VALUES(7,\'Network user\'); CREATE TABLE site_2_posts(ID INTEGER PRIMARY KEY,post_status TEXT); INSERT INTO site_2_posts VALUES(11,\'publish\'),(12,\'draft\'); CREATE TABLE site_2_postmeta(post_id INTEGER,meta_key TEXT,meta_value TEXT); INSERT INTO site_2_postmeta VALUES(11,\'color\',\'blue\'),(12,\'color\',\'red\');' );
check( DatabaseRow::value( DatabaseRow::from_table( 'users' )->where( 'ID', 7 ), 'display_name' ) === 'Network user', 'Native users resolve the network prefix' );
$query = DatabaseRow::from_table( 'postmeta', 'pm' )->join( 'posts as p', 'p.ID', '=', 'pm.post_id' )->where( 'p.post_status', 'publish' );
check( DatabaseRow::values( $query, 'pm.meta_value' ) === [ 'blue' ], 'Aliased joins preserve published-only reads and raw values' );
check(
    ! array_filter(
        array_keys( $hooks ), function ( $name ) { return strpos( $name, 'craf_appna_model_' ) === 0;
        }
    ), 'Query-only rows introduce no model lifecycle listeners'
);

$binding_schema  = Schema::create(
    'craf_appna_event_bindings', [
        Setup::class,
        'notification_bindings'
    ], true
);
$sequence_schema = Schema::create(
    'craf_appna_event_sequences', [
        Setup::class,
        'notification_sequences'
    ], true
);
check( strpos( $binding_schema, 'site_2_craf_appna_event_bindings' ) !== false && strpos( $binding_schema, 'KEY app_bindings (app_id)' ) !== false, 'Schema preview retains per-site binding names and app index' );
check( strpos( $sequence_schema, 'PRIMARY KEY  (entity_type, entity_id)' ) !== false, 'Schema preview retains the composite sequence key' );

function get_the_excerpt( $post ) {
    return apply_filters( 'get_the_excerpt', $post->post_content );
}

class WP_Post {
    public $post_content = 'Listing text';
}
$renderer        = new class {
    use ListingIntegrationHelpers;

    protected function catalog(): ListingCatalog {
        throw new LogicException( 'Catalog is not used by rendering.' );
    }

    public function render_content( WP_Post $post ): string {
        return $this->apply_listing_content_filters( $post );
    }

    public function render_excerpt( WP_Post $post ): string {
        return $this->get_listing_excerpt( $post );
    }
};
$listing_fixture = new WP_Post();
$previous        = (object) [ 'marker' => 'native context' ];
foreach ( [
    'render_content' => 'the_content',
    'render_excerpt' => 'get_the_excerpt'
] as $method => $hook ) {
    $GLOBALS['post'] = $previous;
    check( $renderer->{$method}( $listing_fixture ) === 'Listing text' && $GLOBALS['post'] === $previous, 'Normal rendering restores native post context' );
    add_filter(
        $hook, function () { throw new RuntimeException( 'native filter failure' );
        }
    );
    try {
        $renderer->{$method}( $listing_fixture );
        throw new LogicException( 'Expected original filter exception.' );
    } catch ( RuntimeException $error ) {
        check( $error->getMessage() === 'native filter failure' && $GLOBALS['post'] === $previous, 'Filter failure propagates and restores native post context' );
    }
    unset( $GLOBALS['post'] );
    try {
        $renderer->{$method}( $listing_fixture );
    } catch ( RuntimeException $error ) {
        check( ! isset( $GLOBALS['post'] ), 'Absent native post context stays absent after failure' );
    }
}
echo "PASS: {$checks} refactor database and native-context checks\n";
