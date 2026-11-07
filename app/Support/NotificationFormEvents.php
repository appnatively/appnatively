<?php

namespace Crafium\AppNatively\App\Support;

defined( 'ABSPATH' ) || exit;
/** Converts successful form hooks to minimal notification metadata. */
class NotificationFormEvents
{
    private static array $request_ids = [];

    private static array $emitted = [];

    public static function accepted( string $integration, string $form_id, string $submission_id = '', ?int $submitter = null ): void {
        if ( ! ctype_digit( $form_id ) || (int) $form_id < 1 ) {
            return;
        }
        if ( $submission_id === '0' ) {
            $submission_id = '';
        }
        if ( null === $submitter ) {
            $submitter = function_exists( 'wp_doing_cron' ) && wp_doing_cron() || defined( 'WP_CLI' ) && WP_CLI ? 0 : get_current_user_id();
        }
        $key = $integration . ':' . $form_id;
        if ( $submission_id === '' ) {
            $submission_id = self::$request_ids[$key] ?? self::$request_ids[$key] = wp_generate_uuid4();
        }
        $id = 'form:' . $key . ':' . $submission_id;
        if ( isset( self::$emitted[$id] ) ) {
            return;
        }
        if ( NotificationEvents::emit(
            'form.submitted', [
                'entity' => [
                    'id'          => $submission_id,
                    'formId'      => $form_id,
                    'integration' => $integration,
                ],
            ], [
                'id'            => $id,
                'entity'        => [
                    'type' => 'submission',
                    'id'   => $submission_id,
                ],
                'relationships' => $submitter > 0 ? [
                    'submitter' => [
                        'externalUserId' => (string) $submitter,
                    ],
                ] : [],
            ]
        ) ) {
            self::$emitted[$id] = true;
        }
    }
}
