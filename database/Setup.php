<?php

namespace Crafium\AppNatively\Database;

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Support\Settings;
use Crafium\AppNatively\App\Support\NotificationEvents;
use Crafium\AppNatively\WpMVC\Database\Schema\Schema;
use Crafium\AppNatively\WpMVC\Database\Schema\Blueprint;

/**
 * Class Setup
 *
 * Initializes owner settings and AppNatively-owned notification tables.
 */
class Setup {
    /**
     * Prepare the plugin's options and tables on activation.
     *
     * @return void
     */
    public function execute() {
        // Mint the Studio connection key up front, so the settings screen has
        // one to show the moment an administrator opens it.
        Settings::get_site_key();

        if ( false === get_option( Settings::OPTION_ENABLED, false ) ) {
            Settings::set_api_enabled( true );
        }

        self::install_notifications();
    }

    /** Activation or explicit owner repair only; native event hooks never migrate. */
    public static function install_notifications(): void {
        if ( (int) get_option( 'craf_appna_events_schema', 0 ) === NotificationEvents::SCHEMA_VERSION ) {
            return;
        }

        // -----------------------------
        // Table prefix
        // -----------------------------
        $prefix = 'craf_appna_';

        // -----------------------------
        // 1. Notification Bindings Table
        // -----------------------------
        Schema::create(
            $prefix . 'event_bindings', [
                self::class,
                'notification_bindings'
            ]
        );

        // -----------------------------
        // 2. Notification Sequences Table
        // -----------------------------
        Schema::create(
            $prefix . 'event_sequences', [
                self::class,
                'notification_sequences'
            ]
        );

        if ( Schema::has_table( $prefix . 'event_bindings' ) && Schema::has_table( $prefix . 'event_sequences' ) ) {
            update_option( 'craf_appna_events_schema', NotificationEvents::SCHEMA_VERSION, false );
        } else {
            delete_option( 'craf_appna_events_schema' );
            NotificationEvents::record_error( 'notification_storage_unavailable' );
        }
    }

    public static function notification_bindings( Blueprint $table ): void {
        $table->string( 'source_id', 100 );
        $table->string( 'app_id', 100 );
        $table->unsigned_big_integer( 'storage_version' )->default( 1 );
        $table->long_text( 'payload', false );
        $table->primary( 'source_id' );
        $table->index( 'app_id', 'app_bindings' );
    }

    public static function notification_sequences( Blueprint $table ): void {
        $table->string( 'entity_type', 32 );
        $table->unsigned_big_integer( 'entity_id' );
        $table->unsigned_big_integer( 'version' );
        $table->primary(
            [
                'entity_type',
                'entity_id'
            ]
        );
    }

    /**
     * Remove the plugin's options.
     *
     * @return void
     */
    public function drop() {
        Settings::delete_all();
    }
}
