<?php

/** Remove only AppNatively-owned data when the owner deletes the plugin. */
defined( 'ABSPATH' ) || exit;
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
$craf_appna_cleanup_site = static function (): void {
    global $wpdb;
    foreach ( [
        'craf_appna_api_enabled',
        'craf_appna_site_key',
        'craf_appna_trusted_proxies',
        'craf_appna_content_types',
        'craf_appna_migrations',
        'craf_appna_events_schema',
        'craf_appna_event_last_error',
        'craf_appna_notifications_enabled',
        'craf_appna_notification_consent',
        'craf_appna_notification_bindings',
    ] as $option ) {
        delete_option( $option );
        delete_site_option( $option );
    }
    foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
        foreach ( $hooks as $hook => $events ) {
            if ( strpos( $hook, 'craf_appna_nq_' ) !== 0 && $hook !== 'craf_appna_notification_dispatch' ) {
                continue;
            }
            foreach ( $events as $event ) {
                wp_unschedule_event( $timestamp, $hook, $event['args'] );
            }
        }
    }
    delete_metadata( 'post', 0, '_craf_appna_event_version', '', true );
    $order_meta = $wpdb->prefix . 'wc_orders_meta';
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $order_meta ) ) === $order_meta ) {
        $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE meta_key=%s', $order_meta, '_craf_appna_event_version' ) );
    }
    foreach ( [
        'craf_appna_event_outbox',
        'craf_appna_event_sequences',
        'craf_appna_event_bindings',
    ] as $table ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- fixed plugin-owned suffix and trusted WordPress prefix.
        $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . $table );
    }
    foreach ( [
        'craf_appna_nq_',
        'craf_appna_setup_',
        '_transient_craf_appna_',
        '_transient_timeout_craf_appna_',
        '_site_transient_craf_appna_',
        '_site_transient_timeout_craf_appna_',
    ] as $prefix ) {
        $keys = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix ) . '%' ) );
        foreach ( $keys as $key ) {
            delete_option( $key );
        }
    }
};
if ( is_multisite() ) {
    $craf_appna_offset = 0;
    do {
        $craf_appna_sites = get_sites(
            [
                'fields' => 'ids',
                'number' => 100,
                'offset' => $craf_appna_offset,
            ]
        );
        foreach ( $craf_appna_sites as $craf_appna_site ) {
            switch_to_blog( (int) $craf_appna_site );
            $craf_appna_cleanup_site();
            restore_current_blog();
        }
        $craf_appna_offset += 100;
    } while ( count( $craf_appna_sites ) === 100 );
    global $wpdb;
    foreach ( [
        'craf_appna_nq_',
        '_site_transient_craf_appna_',
        '_site_transient_timeout_craf_appna_',
    ] as $craf_appna_prefix ) {
        $craf_appna_rows = $wpdb->get_results( $wpdb->prepare( "SELECT site_id,meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s", $wpdb->esc_like( $craf_appna_prefix ) . '%' ), ARRAY_A );
        foreach ( $craf_appna_rows as $craf_appna_row ) {
            delete_network_option( (int) $craf_appna_row['site_id'], $craf_appna_row['meta_key'] );
        }
    }
} else {
    $craf_appna_cleanup_site();
}
foreach ( [
    '_craf_appna_auth_tokens',
    '_craf_appna_cookie_sessions',
    '_craf_appna_session_cutoff',
] as $craf_appna_key ) {
    delete_metadata( 'user', 0, $craf_appna_key, '', true );
}
