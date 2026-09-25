<?php
/**
 * Remove everything this plugin stored.
 *
 * Runs when the plugin is deleted from the Plugins screen. Deliberately does
 * not load the plugin's bootstrap — WordPress calls this file directly and the
 * container is not available — so the option and meta keys are repeated here
 * as literals. Keep them in step with App\Support\Settings and App\Support\Auth.
 *
 * @package Crafium\AppNatively
 */

// WP_UNINSTALL_PLUGIN is the guard that matters here — WordPress only defines
// it when it is genuinely uninstalling this plugin. ABSPATH is checked too so
// the file cannot be reached directly under any circumstances.
defined( 'ABSPATH' ) || exit;
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$craf_appna_options = [
    'craf_appna_api_enabled',
    'craf_appna_site_key',
    'craf_appna_trusted_proxies',
    'craf_appna_content_types',
    'craf_appna_migrations',
];

foreach ( $craf_appna_options as $craf_appna_option ) {
    delete_option( $craf_appna_option );
    delete_site_option( $craf_appna_option );
}

// Every user's issued app tokens.
delete_metadata( 'user', 0, '_craf_appna_auth_tokens', '', true );

// Transients: rate-limit counters and unredeemed autologin tokens. These all
// expire on their own, but leaving them behind on an explicit uninstall is
// untidy, and there is no delete_transient() equivalent for a prefix.
//phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like( '_transient_craf_appna_' ) . '%',
        $wpdb->esc_like( '_transient_timeout_craf_appna_' ) . '%'
    )
);

if ( is_multisite() ) {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
            $wpdb->esc_like( '_site_transient_craf_appna_' ) . '%',
            $wpdb->esc_like( '_site_transient_timeout_craf_appna_' ) . '%'
        )
    );
}
//phpcs:enable

wp_cache_flush();
