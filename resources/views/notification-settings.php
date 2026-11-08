<?php

defined( "ABSPATH" ) || exit;
echo '<div class="wrap"><h1>' . esc_html__( 'AppNatively Notifications', 'appnatively' ) . '</h1>';
echo '<p>' . esc_html__( 'Choose whether to share event metadata with AppNatively for mobile notifications.', 'appnatively' ) . '</p>';
echo '<p>' . esc_html__( 'When Send notification events is on and Studio is connected, the plugin automatically sends order, listing and submission IDs/statuses, related user IDs, account roles and session revocations to your configured AppNatively service. Authenticated audience requests can also read names and email addresses. Built-in form events exclude submitted field values. Login tokens and passwords are never sent.', 'appnatively' ) . '</p>';
echo '<p><a href="https://appnatively.com/privacy-policy" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Privacy policy', 'appnatively' ) . '</a> · <a href="https://appnatively.com/terms-and-conditions" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Terms of service', 'appnatively' ) . '</a></p>';
echo '<form method="post">';
wp_nonce_field( 'craf_appna_notifications' );
echo '<input type="hidden" name="craf_appna_save_notifications" value="1">';
echo '<p><label><input type="checkbox" name="craf_appna_notifications_enabled" value="1" ' . checked( $sending_enabled, true, false ) . '> ' . esc_html__( 'Send notification events', 'appnatively' ) . '</label></p>';
submit_button( __( 'Save notification settings', 'appnatively' ) );
echo '</form><p>' . esc_html__( 'Send notification events is on by default. Use Check Connection in Studio to connect notification delivery. To turn notifications off or back on, change this option and save. Turning sending off cancels pending work; an already in-flight request can finish.', 'appnatively' ) . '</p>';
echo '<h2>' . esc_html__( 'Delivery status', 'appnatively' ) . '</h2>';
$error = get_option( 'craf_appna_event_last_error', [] );
if ( is_array( $error ) && ! empty( $error['code'] ) ) {
    echo '<p>' . esc_html( $error['code'] ) . '</p>';
}
echo '<p>' . esc_html__( 'Notification events are sent through a background queue. If delivery is delayed, check the service connection before retrying paused jobs.', 'appnatively' ) . '</p>';
echo '<form method="post">';
wp_nonce_field( 'craf_appna_retry_events' );
echo '<input type="hidden" name="craf_appna_retry_events" value="1">';
submit_button( __( 'Retry paused notification jobs', 'appnatively' ) );
echo '</form></div>';
