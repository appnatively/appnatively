<?php

namespace Crafium\AppNatively\App\Support;

defined( "ABSPATH" ) || exit;
use Crafium\AppNatively\App\Support\NotificationEvents as Events;
class NotificationSettingsPage
{
    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( isset( $_POST['craf_appna_save_notifications'] ) && check_admin_referer( 'craf_appna_notifications' ) ) {
            $enabled = isset( $_POST['craf_appna_notifications_enabled'] );
            update_option( 'craf_appna_notifications_enabled', $enabled ? 'yes' : 'no', false );
            if ( $enabled ) {
                Events::install();
                if ( (int) get_option( 'craf_appna_events_schema', 0 ) !== Events::SCHEMA_VERSION ) {
                    Events::record_error( 'notification_storage_unavailable' );
                }
            } else {
                // Fence off old jobs even if queue cleanup is interrupted.
                update_option( 'craf_appna_notification_epoch', wp_generate_uuid4(), false );
                Events::cancel_queues();
            }
        }
        if ( isset( $_POST['craf_appna_retry_events'] ) && check_admin_referer( 'craf_appna_retry_events' ) ) {
            Events::retry_blocked();
        }
        $sending = get_option( 'craf_appna_notifications_enabled', null );
        \Crafium\AppNatively\WpMVC\View\View::render(
            "notification-settings", [
                "sending_enabled" => null === $sending || 'yes' === $sending,
            ]
        );
    }
}
