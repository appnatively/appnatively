<?php

// phpcs:disable WordPress.Security.EscapeOutput -- Synthetic CLI results, never a public endpoint.
require __DIR__ . '/security-wordpress-bootstrap.php';

use Crafium\AppNatively\App\Support\Auth;
use Crafium\AppNatively\App\Support\AuthorizationStore as Store;
use Crafium\AppNatively\App\Support\CheckoutHandoff as Handoff;
use Crafium\AppNatively\App\Support\ClientAddress;
use Crafium\AppNatively\App\Support\Settings;
use Crafium\AppNatively\App\Support\AuthorizationStorageException;
use Crafium\AppNatively\App\Integrations\Forms\FluentForm;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

$checks  = 0;
$results = [];

function check( $condition, string $message ): void {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
    ++$GLOBALS['checks'];
    $GLOBALS['results'][] = $message;
}

function storage_failure( callable $operation ): bool {
    try {
        $operation();
    } catch ( AuthorizationStorageException $error ) {
        return $error->getCode() === 503;
    }
    return false;
}

function bearer( string $token ): WP_REST_Request {
    $request = new WP_REST_Request();
    $request->set_header( 'Authorization', 'Bearer ' . $token );
    return $request;
}

function worker( string $operation, $argument ): array {
    $pipes   = [];
    $process = proc_open(
        [
            PHP_BINARY,
            __DIR__ . '/security-worker.php',
            $GLOBALS['socket'],
            $operation,
            (string) $argument
        ], [
            0 => [
                'pipe',
                'r'
            ],
            1 => [
                'pipe',
                'w'
            ],
            2 => [
                'pipe',
                'w'
            ]
        ], $pipes
    );
    fclose( $pipes[0] );
    return [
        $process,
        $pipes
    ];
}

function finish_worker( array $worker ): array {
    [$process, $pipes] = $worker;
    $out               = stream_get_contents( $pipes[1] );
    $err               = stream_get_contents( $pipes[2] );
    fclose( $pipes[1] );
    fclose( $pipes[2] );
    $exit = proc_close( $process );
    if ( $exit !== 0 || ! is_array( json_decode( $out, true ) ) ) {
        throw new RuntimeException( 'Worker failed: ' . $out . $err );
    }
    return json_decode( $out, true );
}

function submit_form( int $id, array $data = [] ): void {
    $request = new WP_REST_Request( 'POST' );
    $request->set_body_params(
        array_merge(
            [
                'form_id' => $id,
                'email'   => 'fixture@example.test'
            ], $data
        )
    );
    ( new FluentForm( \Crafium\AppNatively\WpMVC\App::instance() ) )->form_submit( new Request( $request ) );
}

function form_rejected( int $id, array $data = [] ): bool {
    try {
        submit_form( $id, $data );
    } catch ( \Crafium\AppNatively\WpMVC\Exceptions\Exception $error ) {
        return $error->getCode() >= 400 && $error->getCode() < 500;
    }
    return false;
}

$user = wp_create_user( 'security-customer', 'synthetic-pass-4581', 'security-customer@example.test' );
Settings::set_api_enabled( true );
$original = Auth::issue( $user );
check( Auth::resolve( bearer( $original ) ) === $user, 'Existing token format resolves without migration' );
wp_cache_set( $user, [Auth::META_KEY => [serialize( [] )]], 'user_meta' );
check( Auth::resolve( bearer( $original ) ) === $user, 'Authentication ignores stale metadata caches' );

foreach ( ['single', 'user', 'global'] as $mode ) {
    $old     = Auth::issue( $user );
    $pending = null;
    Store::locked(
        static function () use ( $user, $old, $mode, &$pending ): void {
            $pending = worker( 'issue', $user );
            usleep( 250000 );
            if ( $mode === 'single' ) {
                Auth::revoke( $old );
            } elseif ( $mode === 'user' ) {
                Auth::revoke_all( $user );
            } else {
                Auth::revoke_everyones();
            }
        }
    );
    $issued = finish_worker( $pending );
    check( isset( $issued['result'] ) && Auth::resolve( bearer( $old ) ) === null && Auth::resolve( bearer( $issued['result'] ) ) === $user, 'Concurrent issuance cannot restore ' . $mode . ' revocation' );
}
delete_user_meta( $user, Auth::META_KEY );
$one = worker( 'issue', $user );
$two = worker( 'issue', $user );
$one = finish_worker( $one );
$two = finish_worker( $two );
check( Auth::count_active( $user ) === 2 && (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE user_id=%d AND meta_key=%s", $user, Auth::META_KEY ) ) === 1, 'Concurrent first issuance creates one snapshot and retains both tokens' );
$snapshot_token = $one['result'];
$wpdb->query( 'START TRANSACTION' );
$wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id=%d AND meta_key=%s", $user, Auth::META_KEY ) );
finish_worker( worker( 'revoke', $snapshot_token ) );
check( Auth::resolve( bearer( $snapshot_token ) ) === null, 'Current credential reads ignore a caller transaction stale snapshot' );
$wpdb->query( 'ROLLBACK' );
$old = Auth::issue( $user );
$wpdb->query( 'START TRANSACTION' );
Auth::revoke( $old );
$pending = worker( 'issue', $user );
usleep( 250000 );
$wpdb->query( 'COMMIT' );
$issued = finish_worker( $pending );
check( Auth::resolve( bearer( $old ) ) === null && isset( $issued['result'] ), 'An uncommitted revocation cannot be bypassed after its advisory lock releases' );
$tokens = [];
for ( $i = 0; $i < Auth::MAX_TOKENS; ++$i ) {
    $tokens[hash( 'sha256', 'old-' . $i )] = [
        'issued'  => time() + 1,
        'expires' => time() + 3600
    ];
}
update_user_meta( $user, Auth::META_KEY, $tokens );
$new = Auth::issue( $user );
check( Auth::count_active( $user ) === 10 && Auth::resolve( bearer( $new ) ) === $user, 'The newly returned token survives timestamp ties and the device cap' );
$snapshot = Auth::get_tokens( $user );
$wpdb->query( 'START TRANSACTION' );
Auth::revoke( $new );
$wpdb->query( 'ROLLBACK' );
check( Auth::get_tokens( $user ) === $snapshot, 'Authorization mutations do not commit caller transactions' );
add_user_meta( $user, Auth::META_KEY, $tokens );
check(
    storage_failure(
        static function () use ( $user ): void { Auth::issue( $user );
        }
    ), 'Ambiguous duplicate rows fail closed'
);
delete_user_meta( $user, Auth::META_KEY );

$pending = null;
Store::locked(
    static function () use ( $user, &$pending ): void {
        $pending = worker( 'issue', $user );
        $result  = finish_worker( $pending );
        check( ( $result['error'] ?? 0 ) === 503, 'Five-second advisory-lock timeout returns no credential' );
    }
);
// Fault injection touches only this disposable database, never real site state.
$broken_read = static function ( $sql ) use ( $wpdb ) {
    return strpos( $sql, 'SELECT umeta_id,meta_value FROM ' . $wpdb->usermeta ) === 0 ? 'SELECT * FROM appna_missing_storage' : $sql;
};
add_filter( 'query', $broken_read );
check(
    storage_failure(
        static function () use ( $user ): void { Auth::get_tokens( $user );
        }
    ), 'Failed credential reads fail closed'
);
$request = bearer( $token ?? $original );
wp_set_current_user( 0 );
$next  = static function (): bool { return true;
};
$error = ( new \Crafium\AppNatively\App\Http\Middleware\EnsureIsAuthenticated() )->handle( $request, $next );
check( is_wp_error( $error ) && $error->get_error_data()['status'] === 503, 'Required-auth permission errors retain HTTP 503 on storage failure' );
$error = ( new \Crafium\AppNatively\App\Http\Middleware\ResolveAppUser() )->handle( $request, $next );
check( is_wp_error( $error ) && $error->get_error_data()['status'] === 503, 'Optional authentication cannot downgrade storage failure to guest access' );
$events = new \Crafium\AppNatively\App\Providers\NotificationEventServiceProvider( \Crafium\AppNatively\WpMVC\App::instance() );
$error  = ( new \Crafium\AppNatively\App\Http\Controllers\NotificationController( $events ) )->authorize_identity( $request );
check( is_wp_error( $error ) && $error->get_error_data()['status'] === 503, 'Notification authorization shares the failing credential reader and HTTP 503' );
remove_filter( 'query', $broken_read );
$token    = Auth::issue( $user );
$changed  = false;
$conflict = static function ( $sql ) use ( $wpdb, $user, &$changed ) {
    if ( ! $changed && strpos( $sql, 'UPDATE ' . $wpdb->usermeta . ' SET meta_value=' ) === 0 ) {
        $changed = true;
        $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->usermeta} SET meta_value=%s WHERE user_id=%d AND meta_key=%s", serialize( [] ), $user, Auth::META_KEY ) );
    }
    return $sql;
};
add_filter( 'query', $conflict );
check(
    storage_failure(
        static function () use ( $user ): void { Auth::issue( $user );
        }
    ), 'Conditional token write conflicts return no new credential'
);
remove_filter( 'query', $conflict );
check( Auth::resolve( bearer( $token ) ) === null, 'Failed conditional write never restores a removed token' );
$token   = Auth::issue( $user );
$cookies = 0;
$cookie  = static function ( $id ) use ( &$cookies, $user ): void { check( $id === $user, 'Cookie targets the bound user' ); ++$cookies;
};
$handoff = Handoff::issue( bearer( $token ), $user );
check( Handoff::redeem( $handoff, $cookie ) && $cookies === 1, 'Healthy app handoff consumes and creates one cookie' );
check( ! Handoff::redeem( $handoff, $cookie ) && $cookies === 1, 'Consumed handoff cannot be replayed' );
$handoff = Handoff::issue( bearer( $token ), $user );
$wpdb->query( 'START TRANSACTION' );
check(
    storage_failure(
        static function () use ( $handoff, $cookie ): void { Handoff::redeem( $handoff, $cookie );
        }
    ) && $cookies === 1, 'Caller transaction cannot roll a consumed handoff back after a cookie'
);
$wpdb->query( 'ROLLBACK' );
check( Handoff::redeem( $handoff, $cookie ), 'Rejected caller transaction leaves the handoff usable after rollback' );
foreach ( ['single', 'user', 'global', 'password', 'api'] as $mode ) {
    $token   = Auth::issue( $user );
    $handoff = Handoff::issue( bearer( $token ), $user );
    if ( $mode === 'single' ) {
        Auth::revoke( $token );
    } elseif ( $mode === 'user' ) {
        Auth::revoke_all( $user );
    } elseif ( $mode === 'global' ) {
        Auth::revoke_everyones();
    } elseif ( $mode === 'password' ) {
        wp_set_password( 'changed-synthetic-pass-4581', $user );
    } else {
        Settings::set_api_enabled( false );
        check( ! Handoff::redeem( $handoff, $cookie ), 'Disabled API cannot redeem an outstanding handoff' );
        Settings::set_api_enabled( true );
    }
    check( ! Handoff::redeem( $handoff, $cookie ), 'Handoff stays invalid after ' . $mode . ' invalidation' );
}
$token              = Auth::issue( $user );
$handoff            = Handoff::issue( bearer( $token ), $user );
$generation_failure = static function ( $sql ) {
    return strpos( $sql, Handoff::API_GENERATION ) !== false && ( strpos( $sql, 'UPDATE ' ) === 0 || strpos( $sql, 'INSERT ' ) === 0 ) ? 'SELECT * FROM appna_missing_storage' : $sql;
};
add_filter( 'query', $generation_failure );
check(
    storage_failure(
        static function (): void { Settings::set_api_enabled( false );
        }
    ), 'Generation-write failure makes API disable unsuccessful'
);
remove_filter( 'query', $generation_failure );
check( Settings::is_api_enabled() && Handoff::redeem( $handoff, $cookie ), 'Failed preflight leaves the enabled setting and valid exchange consistent' );
$handoff = Handoff::issue( bearer( $token ), $user );
Settings::set_api_enabled( false );
Settings::set_api_enabled( false );
Settings::set_api_enabled( true );
check( ! Handoff::redeem( $handoff, $cookie ), 'Disable retries cannot bypass handoff invalidation' );
delete_option( Settings::OPTION_ENABLED );
$handoff = Handoff::issue( bearer( $token ), $user );
Settings::set_api_enabled( false );
Settings::set_api_enabled( true );
check( ! Handoff::redeem( $handoff, $cookie ), 'Missing API option still invalidates on disable and re-enable' );
delete_option( Settings::OPTION_ENABLED );
$handoff = Handoff::issue( bearer( $token ), $user );
add_option( Settings::OPTION_ENABLED, 'no' );
update_option( Settings::OPTION_ENABLED, 'yes' );
check( ! Handoff::redeem( $handoff, $cookie ), 'Direct WordPress option add/update also invalidates before disable' );
$between = null;
$race    = static function ( $value ) use ( $token, $user, &$between ) {
    if ( $value === 'no' ) {
        $between = Handoff::issue( bearer( $token ), $user );
    }
    return $value;
};
add_filter( 'pre_update_option_' . Settings::OPTION_ENABLED, $race );
update_option( Settings::OPTION_ENABLED, 'no' );
remove_filter( 'pre_update_option_' . Settings::OPTION_ENABLED, $race );
update_option( Settings::OPTION_ENABLED, 'yes' );
check( is_string( $between ) && ! Handoff::redeem( $between, $cookie ), 'Direct option-write interleaving cannot preserve a handoff across off/on' );
$wp_session                = WP_Session_Tokens::get_instance( $user )->create( time() + 3600 );
$_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie( $user, time() + 3600, 'logged_in', $wp_session );
$handoff                   = Handoff::issue( new WP_REST_Request(), $user );
check( Handoff::redeem( $handoff, $cookie ), 'Verified WordPress sessions retain checkout compatibility' );
$handoff = Handoff::issue( new WP_REST_Request(), $user );
WP_Session_Tokens::get_instance( $user )->destroy( $wp_session );
check( ! Handoff::redeem( $handoff, $cookie ), 'WordPress session logout invalidates its outstanding handoff' );
$wp_session                = WP_Session_Tokens::get_instance( $user )->create( time() + 3600 );
$_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie( $user, time() + 3600, 'logged_in', $wp_session );
foreach ( ['user', 'global'] as $mode ) {
    $handoff = Handoff::issue( new WP_REST_Request(), $user );
    if ( $mode === 'user' ) {
        Auth::revoke_all( $user );
    } else {
        Auth::revoke_everyones();
    }
    check( ! Handoff::redeem( $handoff, $cookie ) && WP_Session_Tokens::get_instance( $user )->verify( $wp_session ), 'Revocation generation invalidates WordPress handoffs without destroying healthy WordPress sessions: ' . $mode );
}
unset( $_COOKIE[LOGGED_IN_COOKIE] );
$token   = Auth::issue( $user );
$handoff = Handoff::issue( bearer( $token ), $user );
$one     = worker( 'redeem', $handoff );
$two     = worker( 'redeem', $handoff );
$one     = finish_worker( $one );
$two     = finish_worker( $two );
check( (int) $one['result'][0] + (int) $two['result'][0] === 1 && $one['result'][1] + $two['result'][1] === 1, 'Concurrent redemption creates exactly one cookie' );
$handoff        = Handoff::issue( bearer( $token ), $user );
$before_cookies = $cookies;
$fail_commit    = static function ( $sql ) { return $sql === 'COMMIT' ? 'SELECT * FROM appna_missing_storage' : $sql;
};
add_filter( 'query', $fail_commit );
check(
    storage_failure(
        static function () use ( $handoff, $cookie ): void { Handoff::redeem( $handoff, $cookie );
        }
    ) && $cookies === $before_cookies, 'Failed durable consume creates no cookie'
);
remove_filter( 'query', $fail_commit );
check( Handoff::redeem( $handoff, $cookie ), 'Rolled-back failed consume can retry successfully' );
$legacy = bin2hex( random_bytes( 32 ) );
set_transient( 'craf_appna_autologin_' . hash( 'sha256', $legacy ), ['user_id' => $user], 60 );
check( ! Handoff::redeem( $legacy, $cookie ), 'Legacy unbound handoffs cannot create cookies' );
$expiry_filter = static function (): int { return 1;
};
add_filter( 'craf_appna_autologin_ttl', $expiry_filter );
$handoff = Handoff::issue( bearer( $token ), $user );
remove_filter( 'craf_appna_autologin_ttl', $expiry_filter );
sleep( 2 );
check( ! Handoff::redeem( $handoff, $cookie ), 'Expired handoffs cannot create cookies' );
Handoff::cleanup();
check( Store::option( Handoff::PREFIX . hash( 'sha256', $handoff ), $wpdb->options ) === null, 'Cleanup removes expired bound handoffs' );

// Real native form processor, models, restrictions and spam hooks.
wp_set_current_user( 0 );
$_SERVER['REMOTE_ADDR'] = '198.51.100.8';
add_filter(
    'craf_appna_form_rate_limit_max', static function (): int { return 1000;
    }
);
add_filter( 'fluentform/prevent_malicious_attacks', '__return_false' );
$fields   = ['fields' => [[
    'element'    => 'input_email',
    'attributes' => [
        'name'  => 'email',
        'value' => '',
        'type'  => 'email'
    ],
    'settings'   => [
        'label'            => 'Email',
        'validation_rules' => [
            'required' => [
                'value'   => true,
                'message' => 'Email required'
            ],
            'email'    => [
                'value'   => true,
                'message' => 'Email invalid'
            ]
        ]
    ],
]
]
];
$form     = \FluentForm\App\Models\Form::insertGetId(
    [
        'title'       => 'Security fixture',
        'status'      => 'published',
        'form_fields' => wp_json_encode( $fields ),
        'created_at'  => current_time( 'mysql' ),
        'updated_at'  => current_time( 'mysql' )
    ]
);
$defaults = \FluentForm\App\Models\Form::getFormsDefaultSettings( $form );
\FluentForm\App\Models\FormMeta::persist( $form, 'formSettings', $defaults );
$hooks = 0;
add_action(
    'fluentform/submission_inserted', static function () use ( &$hooks ): void { ++$hooks;
    }
);
submit_form( $form );
check( $hooks === 1 && \FluentForm\App\Models\Submission::where( 'form_id', $form )->count() === 1, 'Native submission inserts and runs submission hooks exactly once' );
check( form_rejected( $form, ['email' => 'invalid'] ), 'Native field validation remains active' );
$restriction = static function (): array { return [
    'status'  => false,
    'message' => 'Native restriction denied'
];
};
add_filter( 'fluentform/is_form_renderable', $restriction );
check( form_rejected( $form ), 'Native renderability restrictions reject AppNatively submissions' );
remove_filter( 'fluentform/is_form_renderable', $restriction );
$nonce = static function (): bool { return true;
};
add_filter( 'fluentform/nonce_verify', $nonce );
check( form_rejected( $form ), 'Required native nonce is not fabricated or bypassed' );
submit_form( $form, ['_fluentform_' . $form . '_fluentformnonce' => wp_create_nonce( 'fluentform-submit-form' )] );
remove_filter( 'fluentform/nonce_verify', $nonce );
check( $hooks === 2, 'Supplied native nonce reaches the native validator' );
$honeypot = static function (): bool { return true;
};
add_filter( 'fluentform/honeypot_status', $honeypot );
check( form_rejected( $form ) && form_rejected( $form, ['item_' . $form . '__fluent_sf' => 'bot'] ), 'Missing and filled native honeypots are rejected' );
submit_form( $form, ['item_' . $form . '__fluent_sf' => ''] );
check( $hooks === 3, 'An explicitly supplied empty honeypot reaches native processing' );
remove_filter( 'fluentform/honeypot_status', $honeypot );
// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Native Fluent Forms hook.
check( ! apply_filters( 'fluentform/disable_captcha', false, (object) ['id' => $form], 'recaptcha' ), 'Website CAPTCHA is unchanged after success and errors' );

$empty_form = \FluentForm\App\Models\Form::insertGetId(
    [
        'title'       => 'Empty security fixture',
        'status'      => 'published',
        'form_fields' => wp_json_encode( ['fields' => []] )
    ]
);
check( form_rejected( $empty_form ), 'Empty form definition cannot silently claim submission success' );
$settings                                            = $defaults;
$settings['restrictions']['requireLogin']['enabled'] = true;
\FluentForm\App\Models\FormMeta::persist( $form, 'formSettings', $settings );
check( form_rejected( $form ), 'Configured login restriction rejects guests' );
wp_set_current_user( $user );
submit_form( $form );
wp_set_current_user( 0 );
$settings                                 = $defaults;
$settings['restrictions']['scheduleForm'] = [
    'enabled'      => true,
    'start'        => '2099-01-01',
    'end'          => '2099-12-31',
    'pendingMsg'   => 'Not open',
    'expiredMsg'   => 'Closed',
    'selectedDays' => []
];
\FluentForm\App\Models\FormMeta::persist( $form, 'formSettings', $settings );
check( form_rejected( $form ), 'Configured future schedule rejects submissions' );
$settings                                         = $defaults;
$settings['restrictions']['limitNumberOfEntries'] = [
    'enabled'         => true,
    'numberOfEntries' => 1,
    'period'          => 'total',
    'limitReachedMsg' => 'Full'
];
\FluentForm\App\Models\FormMeta::persist( $form, 'formSettings', $settings );
check( form_rejected( $form ), 'Configured native entry limit rejects extra submissions' );
\FluentForm\App\Models\FormMeta::persist( $form, 'formSettings', $defaults );
$token_protection = static function (): bool { return true;
};
add_filter( 'fluentform/token_based_spam_protection_status', $token_protection );
check( form_rejected( $form ), 'Missing native anti-spam token retains the REST error envelope' );
$token_fields = \FluentForm\App\Modules\Form\TokenBasedSpamProtection::getConversationalTokenInput( $form );
submit_form( $form, $token_fields );
remove_filter( 'fluentform/token_based_spam_protection_status', $token_protection );
check( ! wp_doing_ajax(), 'Native spam-response interception restores AJAX state' );
$captcha = static function (): bool { return true;
};
add_filter( 'fluentform/has_recaptcha', $captcha );
update_option(
    '_fluentform_reCaptcha_details', [
        'secretKey'   => 'fixture-only',
        'siteKey'     => 'fixture-only',
        'api_version' => 'v2_visible'
    ]
);
submit_form( $form );
$native_denied = false;
try {
    ( new \FluentForm\App\Services\Form\SubmissionHandlerService() )->handleSubmission( ['email' => 'fixture@example.test'], $form );
} catch ( \FluentForm\Framework\Validator\ValidationException $error ) {
    $native_denied = true;
}
remove_filter( 'fluentform/has_recaptcha', $captcha );
check( $native_denied, 'Only AppNatively skips CAPTCHA; native website submissions still require it' );
class Akismet {
    public static function get_api_key() {
        return 'fixture-only';
    }

    public static function http_post( $data, $path ) {
        return [
            '',
            'true'
        ];
    }
}
update_option(
    '_fluentform_global_form_settings', ['misc' => [
        'akismet_status'     => 'yes',
        'akismet_validation' => 'mark_as_spam_and_skip_processing'
    ]
    ]
);
$before_hooks = $hooks;
submit_form( $form, ['_wp_http_referer' => '/fixture'] );
check( $hooks === $before_hooks && \FluentForm\App\Models\Submission::where( 'form_id', $form )->where( 'status', 'spam' )->count() === 1, 'Native spam quarantine preserves the entry and suppresses actions' );
update_option(
    '_fluentform_global_form_settings', ['misc' => [
        'akismet_status'     => 'yes',
        'akismet_validation' => 'validation_failed'
    ]
    ]
);
check( form_rejected( $form, ['_wp_http_referer' => '/fixture'] ), 'Native spam rejection does not publish an accepted submission' );

// Exercise actual authentication counters with a fixed socket peer and forged headers.
$_SERVER['REMOTE_ADDR'] = '198.51.100.222';
$controller             = new \Crafium\AppNatively\App\Http\Controllers\Auth\AuthController();
$statuses               = [];
for ( $i = 0; $i < 9; ++$i ) {
    $_SERVER['HTTP_CLIENT_IP']       = '203.0.113.' . ( $i + 1 );
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.' . ( $i + 1 );
    $request                         = new WP_REST_Request( 'POST' );
    $request->set_body_params(
        [
            'email'    => 'security-customer@example.test',
            'password' => 'wrong-password'
        ]
    );
    try {
        $controller->login( new Request( $request ) );
    } catch ( Throwable $error ) {
        $statuses[] = $error->getCode();
    }
}
check(
    $statuses === [
        401,
        401,
        401,
        401,
        401,
        401,
        401,
        401,
        429
    ], 'Forged headers cannot reset the existing eight-failure login limit'
);

$token   = Auth::issue( $user );
$handoff = Handoff::issue( bearer( $token ), $user );
check( $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name=%s", Handoff::PREFIX . hash( 'sha256', $handoff ) ) ) === 'no', 'Checkout records are non-autoloaded' );
define( 'WP_UNINSTALL_PLUGIN', 'appnatively/appnatively.php' );
require dirname( __DIR__, 2 ) . '/uninstall.php';
check( Store::option( Handoff::PREFIX . hash( 'sha256', $handoff ), $wpdb->options ) === null && ! wp_next_scheduled( Handoff::CLEANUP_HOOK ), 'Uninstall removes owned checkout records and cleanup schedule' );

echo 'PASS: ' . $checks . " native security checks\n";
file_put_contents(
    dirname( $socket ) . '/security-results.json', wp_json_encode(
        [
            'checks'  => $checks,
            'results' => $results
        ], JSON_PRETTY_PRINT
    )
);
