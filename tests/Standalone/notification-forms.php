<?php

// phpcs:disable WordPress.Security.EscapeOutput -- CLI-only test diagnostics and protocol fixtures are not HTML.

namespace FluentForm\App\Models {
    class Submission
    {
        public static $entries = [];

        static function find( $id ) {
            return self::$entries[$id] ?? null;
        }
    }
}
namespace {
    require __DIR__ . '/notification-fixture.php';
    use Crafium\AppNatively\App\Support\NotificationEvents as Events;
    use Crafium\AppNatively\App\Support\NotificationFormEvents as Forms;
    Events::save_binding( fixture_binding() );
    $provider = new \Crafium\AppNatively\App\Providers\NotificationEventServiceProvider();
    $provider->boot();
    class FrmEntry
    {
        static $entry;

        // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Match the third-party plugin API.
        static function getOne( $id ) {
            return self::$entry;
        }
    }

    // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- Match the third-party plugin API.
    function Ninja_Forms() {
        return new class
        {
            function form() {
                return $this;
            }

            function sub( $id ) {
                return $this;
            }

            function get() {
                return $this;
            }

            function get_form_id() {
                return 123;
            }
        };
    }

    $cases  = [
        'fluentform'     => function ( $id ) {
            \FluentForm\App\Models\Submission::$entries[$id] = (object) [
                'id'      => $id,
                'form_id' => 123,
                'status'  => 'unread',
            ];
            do_action(
                // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Established third-party hook name.
                'fluentform/submission_inserted', $id, [
                    'secret' => 'not-stored',
                ], (object) [
                    'id' => 123,
                ]
            );
        },
        'formidable'     => function ( $id ) {
            FrmEntry::$entry = (object) [
                'is_draft' => false,
            ];
            do_action( 'frm_after_create_entry', $id, 123 );
        },
        'weforms'        => function ( $id ) {
            do_action( 'weforms_entry_submission', $id, 123 );
        },
        'formgent'       => function ( $id ) {
            do_action(
                'formgent_after_create_form_response', $id, (object) [
                    'ID' => 123,
                ]
            );
        },
        'forminator'     => function ( $id ) {
            do_action(
                'forminator_after_handle_form', (object) [
                    'entry_id' => $id,
                    'form_id'  => 123,
                    'status'   => 'active',
                    'is_spam'  => false,
                ]
            );
        },
        'contact-form-7' => function ( $id ) {
            do_action(
                'wpcf7_submit', new class
                {
                    function id() {
                        return 123;
                    }
                }, [
                    'status' => 'mail_sent',
                ]
            );
        },
        'wpforms'        => function ( $id ) {
            do_action(
                'wpforms_process_complete', [
                    'secret' => 'not-stored',
                ], [], [
                    'id' => 123,
                ], $id
            );
        },
        'ninjaforms'     => function ( $id ) {
            do_action( 'ninja_forms_save_sub', $id );
        },
        'everest-forms'  => function ( $id ) {
            do_action(
                'everest_forms_process_complete', [
                    'secret' => 'not-stored',
                ], [], [
                    'id' => 123,
                ], $id
            );
        },
        'sureforms'      => function ( $id ) {
            do_action(
                'srfm_form_submit', [
                    'success'  => true,
                    'form_id'  => 123,
                    'entry_id' => $id,
                ]
            );
        },
        'happyforms'     => function ( $id ) {
            do_action(
                'happyforms_submission_success', [
                    'secret' => 'not-stored',
                    'ID'     => $id,
                ], [
                    'ID' => 123,
                ]
            );
        },
        'gutena-forms'   => function ( $id ) {
            do_action(
                'gutena_forms_submission', [
                    'formID'   => 123,
                    'raw_data' => [
                        'secret' => 'not-stored',
                    ],
                ]
            );
        },
        'bitform'        => function ( $id ) {
            apply_filters(
                'bitform_filter_return_submit_success', [
                    'entry_id' => $id,
                ], 123
            );
        },
    ];
    $checks = 0;

    function check( $value, $message ) {
        if ( ! $value ) {
            throw new RuntimeException( $message );
        }
        $GLOBALS['checks']++;
    }

    foreach ( $cases as $integration => $callback ) {
        Events::queue( 'source', 'events' )->discard();
        $current_user = 7;
        $id           = 1000 + $checks;
        $callback( $id );
        $callback( $id );
        $rows = queued_items();
        check( count( $rows ) === 1, "{$integration} native hooks deduplicate" );
        $event = $rows[0]['event'];
        check(
            $event['relationships'] === [
                'submitter' => [
                    'externalUserId' => '7',
                ],
            ], "{$integration} native submitter"
        );
        check( strpos( json_encode( $rows ), 'not-stored' ) === false, "{$integration} discards submitted fields" );
        Events::queue( 'source', 'events' )->discard();
        foreach ( [
            'request_ids',
            'emitted',
        ] as $property ) {
            $prop = new ReflectionProperty( Forms::class, $property );
            $prop->setAccessible( true );
            $prop->setValue( null, [] );
        }
        $current_user = 0;
        $callback( $id + 1 );
        $event = queued_items()[0]['event'];
        check( $event['relationships'] === [], "{$integration} guest never becomes an account" );
    }
    Events::queue( 'source', 'events' )->discard();
    $current_user = 7;
    foreach ( [
        'mail_failed',
        'spam',
        'validation_failed',
        'aborted',
    ] as $status ) {
        do_action(
            'wpcf7_submit', new class
            {
                function id() {
                    return 124;
                }
            }, [
                'status' => $status,
            ]
        );
    }
    check( ! queued_items(), 'CF7 failed mail and rejected submissions never become success events' );
    do_action(
        'srfm_form_submit', [
            'success' => false,
            'form_id' => 124,
        ]
    );
    do_action(
        'forminator_after_handle_form', (object) [
            'entry_id' => 99,
            'form_id'  => 124,
            'is_spam'  => true,
        ]
    );
    FrmEntry::$entry = (object) [
        'is_draft' => true,
    ];
    do_action( 'frm_after_create_entry', 99, 124 );
    \FluentForm\App\Models\Submission::$entries[99] = (object) [
        'form_id' => 124,
        'status'  => 'spam',
    ];
    // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Established third-party hook name.
    do_action( 'fluentform/submission_inserted', 99, [], null );
    check( ! queued_items(), 'Spam, drafts and failed flags are excluded' );
    do_action(
        'forminator_after_handle_form', (object) [
            'entry_id' => 0,
            'form_id'  => 124,
            'status'   => 'active',
            'is_spam'  => false,
        ]
    );
    check( count( queued_items() ) === 1, 'Accepted Forminator submission with storage disabled queues metadata' );
    Events::queue( 'source', 'events' )->discard();
    $cron         = true;
    $current_user = 9;
    Forms::accepted( 'formidable', '124', '45' );
    check( queued_items()[0]['event']['relationships'] === [], 'Background worker is never substituted as submitter' );
    Events::queue( 'source', 'events' )->discard();
    \FluentForm\App\Models\Submission::$entries[999] = (object) [
        'form_id' => 124,
        'status'  => 'unread',
        'user_id' => 7,
    ];
    // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Established third-party hook name.
    do_action( 'fluentform/submission_inserted', 999, [], null );
    check( queued_items()[0]['event']['relationships']['submitter']['externalUserId'] === '7', 'Background callbacks use trusted stored ownership' );
    $original = [
        'entry_id' => 678,
        'extra'    => 'unchanged',
    ];
    check( apply_filters( 'bitform_filter_return_submit_success', $original, 124 ) === $original, 'Native filter result remains identical' );
    Events::queue( 'source', 'events' )->discard();
    $wpdb->fail = 'INSERT INTO options';
    check(
        apply_filters(
            'bitform_filter_return_submit_success', [
                'entry_id' => 679,
            ], 124
        ) === [
            'entry_id' => 679,
        ], 'Queue failure does not change native filter result'
    );
    $wpdb->fail = '';
    check( ! $requests && ! $loopbacks, 'No form hook sends cloud HTTP or dispatches a loopback inline' );
    echo "PASS: {$checks} form hook checks across 13 integrations (offline)\n";
}
