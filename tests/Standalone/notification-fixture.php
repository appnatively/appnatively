<?php

// phpcs:disable WordPress.Security.EscapeOutput -- CLI-only test diagnostics and protocol fixtures are not HTML.
// phpcs:disable WordPress.DB.RestrictedClasses -- Synthetic database adapters test without loading site configuration.

/** Real bundled WpMVC Queue with synthetic WordPress APIs and disposable SQLite. */
namespace Crafium\AppNatively\WpMVC\Contracts {
    class Provider
    {
    }
}
namespace Crafium\AppNatively\WpMVC\Routing\Contracts {
    interface Middleware
    {
    }
}
namespace Crafium\AppNatively\App\Support {
    class Settings
    {
        const OPTION_ENABLED = 'craf_appna_api_enabled';

        public static $enabled = true;

        public static function is_api_enabled(): bool {
            return self::$enabled;
        }

        public static function verify_site_key( $key ) {
            return $key === 'owner-key';
        }

        public static function api_disabled_error() {
            return new \WP_Error( 'disabled' );
        }
    }
}
namespace {
    if ( PHP_SAPI !== 'cli' ) {
        exit;
    }
    set_error_handler(
        function ( $severity, $message, $file, $line ) {
            if ( error_reporting() & $severity ) {
                throw new \ErrorException( $message, 0, $severity, $file, $line );
            }
            return false;
        }
    );
    define( 'ABSPATH', '/unused/' );
    define( 'DAY_IN_SECONDS', 86400 );
    define( 'MINUTE_IN_SECONDS', 60 );
    define( 'ARRAY_A', 'ARRAY_A' );
    // Load real model/query classes while retaining the synthetic WordPress surface.
    spl_autoload_register(
        function ( $class ) {
            $prefixes = [
                'Crafium\\AppNatively\\WpMVC\\Database\\' => dirname( __DIR__, 2 ) . '/vendor/vendor-src/wpmvc/database/src/',
                'Crafium\\AppNatively\\App\\'             => dirname( __DIR__, 2 ) . '/app/',
                'Crafium\\AppNatively\\Database\\'        => dirname( __DIR__, 2 ) . '/database/',
            ];
            foreach ( $prefixes as $prefix => $directory ) {
                if ( strpos( $class, $prefix ) === 0 ) {
                    $file = $directory . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
                    if ( is_file( $file ) ) {
                        require_once $file;
                    }
                    return;
                }
            }
        }
    );
    $hooks        = [];
    $filters      = [];
    $options      = [
        'craf_appna_events_schema' => 5,
    ];
    $current_user = 7;
    $requests     = [];
    $loopbacks    = [];
    $reply_code   = 200;
    $reply_body   = '{}';
    $cron         = false;
    $scheduled    = [];
    $transients   = [];

    function add_action( $name, $callback, ...$args ) {
        $GLOBALS['hooks'][$name][] = $callback;
    }

    function add_filter( $name, $callback, ...$args ) {
        $GLOBALS['filters'][$name][] = $callback;
    }

    // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionDoubleUnderscore, PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.FunctionDoubleUnderscore -- Stub the existing WordPress callback API.
    function __return_true() {
        return true;
    }

    function do_action( $name, ...$args ) {
        foreach ( $GLOBALS['hooks'][$name] ?? [] as $fn ) {
            $fn( ...$args );
        }
    }

    function apply_filters( $name, $value, ...$args ) {
        foreach ( $GLOBALS['filters'][$name] ?? [] as $fn ) {
            $value = $fn( $value, ...$args );
        }
        return $value;
    }

    function get_option( $name, $default = '' ) {
        if ( array_key_exists( $name, $GLOBALS['options'] ) ) {
            return $GLOBALS['options'][$name];
        }
        global $wpdb;
        $raw = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM options WHERE option_name=%s', $name ) );
        return $raw === null || $raw === false ? $default : maybe_unserialize( $raw );
    }

    function update_option( $name, $value, ...$args ) {
        global $wpdb;
        $saved = $wpdb->query( $wpdb->prepare( 'INSERT INTO options(option_name,option_value) VALUES(%s,%s) ON CONFLICT(option_name) DO UPDATE SET option_value=excluded.option_value', $name, is_array( $value ) || is_object( $value ) ? serialize( $value ) : (string) $value ) );
        if ( $saved === false ) {
            return false;
        }
        $GLOBALS['options'][$name] = $value;
        return true;
    }

    function add_option( $name, $value, ...$args ) {
        if ( get_option( $name, null ) !== null ) {
            return false;
        }
        return update_option( $name, $value );
    }

    function wp_http_validate_url( $url ) {
        return is_string( $url ) && filter_var( $url, FILTER_VALIDATE_URL ) && strpos( $url, 'localhost' ) === false;
    }

    function wp_parse_url( $url, $part ) {
        return parse_url( $url, $part );
    }

    function delete_option( $name ) {
        global $wpdb;
        unset( $GLOBALS['options'][$name] );
        return $wpdb->query( $wpdb->prepare( 'DELETE FROM options WHERE option_name=%s', $name ) ) !== false;
    }

    function get_site_option( ...$args ) {
        return get_option( ...$args );
    }

    function update_site_option( ...$args ) {
        return update_option( ...$args );
    }

    function delete_site_option( ...$args ) {
        return delete_option( ...$args );
    }

    function get_site_transient( $name ) {
        return $GLOBALS['transients'][$name] ?? false;
    }

    function set_site_transient( $name, $value, $ttl ) {
        $GLOBALS['transients'][$name] = $value;
        return true;
    }

    function delete_site_transient( $name ) {
        unset( $GLOBALS['transients'][$name] );
        return true;
    }

    function wp_next_scheduled( $hook ) {
        return $GLOBALS['scheduled'][$hook] ?? false;
    }

    function wp_schedule_event( $time, $interval, $hook ) {
        $GLOBALS['scheduled'][$hook] = $time;
        return true;
    }

    function wp_unschedule_event( $time, $hook ) {
        unset( $GLOBALS['scheduled'][$hook] );
    }

    function wp_salt( $scope ) {
        return 'isolated-notification-fixture-salt';
    }

    function wp_json_encode( $value, $flags = 0 ) {
        return json_encode( $value, $flags );
    }

    function wp_generate_uuid4() {
        return bin2hex( random_bytes( 16 ) );
    }

    function wp_rand() {
        return random_int( 0, PHP_INT_MAX );
    }

    function get_current_user_id() {
        return $GLOBALS['current_user'];
    }

    function get_current_blog_id() {
        return 1;
    }

    function wp_set_current_user( $id ) {
        $GLOBALS['current_user'] = $id;
    }

    function wp_doing_cron() {
        return $GLOBALS['cron'];
    }

    function wp_doing_ajax() {
        return false;
    }

    function is_multisite() {
        return false;
    }

    function wp_cache_delete( ...$args ) {
        if ( ( $args[1] ?? '' ) === 'options' ) {
            unset( $GLOBALS['options'][$args[0]] );
        }
    }

    function maybe_serialize( $value ) {
        return is_array( $value ) || is_object( $value ) ? serialize( $value ) : $value;
    }

    function absint( $value ) {
        return abs( (int) $value );
    }

    function is_serialized( $value ) {
        return is_string( $value ) && preg_match( '/^(?:a|O|s|i|b|d|N):/', $value );
    }

    function maybe_unserialize( $value ) {
        return is_serialized( $value ) ? unserialize( $value ) : $value;
    }

    function get_userdata( $id ) {
        return $id > 0 && $id < 100 ? new WP_User( $id ) : false;
    }

    function get_user_meta( $id, $key, $single ) {
        global $wpdb;
        $value = $wpdb->get_var( $wpdb->prepare( 'SELECT meta_value FROM usermeta WHERE user_id=%d AND meta_key=%s', $id, $key ) );
        return $value === false || $value === null ? [] : unserialize( $value );
    }

    function update_user_meta( $id, $key, $value ) {
        global $wpdb;
        $check = apply_filters( 'update_user_metadata', null, $id, $key, $value );
        if ( $check !== null ) {
            return (bool) $check;
        }
        $ok = $wpdb->query( $wpdb->prepare( 'INSERT INTO usermeta(user_id,meta_key,meta_value) VALUES(%d,%s,%s) ON CONFLICT(user_id,meta_key) DO UPDATE SET meta_value=excluded.meta_value', $id, $key, serialize( $value ) ) ) !== false;
        if ( $ok ) {
            do_action( 'updated_user_meta', 1, $id, $key, $value );
        }
        return $ok;
    }

    function delete_user_meta( $id, $key ) {
        global $wpdb;
        $ids = $wpdb->get_col( $wpdb->prepare( 'SELECT umeta_id FROM usermeta WHERE user_id=%d AND meta_key=%s', $id, $key ) );
        do_action( 'delete_user_meta', $ids, $id, $key );
        $ok = $wpdb->query( $wpdb->prepare( 'DELETE FROM usermeta WHERE user_id=%d AND meta_key=%s', $id, $key ) ) !== false;
        if ( $ok ) {
            do_action( 'deleted_user_meta', $ids, $id, $key );
        }
        return $ok;
    }

    function wp_roles() {
        return new class
        {
            function is_role( $role ) {
                return in_array(
                    $role, [
                        'customer',
                        'subscriber',
                    ], true
                );
            }

            function get_names() {
                return [
                    'customer' => 'Customer',
                ];
            }
        };
    }

    function translate_user_role( $role ) {
        return $role;
    }

    function home_url() {
        return 'https://example.test';
    }

    function admin_url( $path ) {
        return 'https://example.test/wp-admin/' . $path;
    }

    function rest_url( $path ) {
        return 'https://example.test/wp-json/' . $path;
    }

    function untrailingslashit( $value ) {
        return rtrim( $value, '/' );
    }

    function is_wp_error( $value ) {
        return $value instanceof WP_Error;
    }

    function sanitize_text_field( $value ) {
        return $value;
    }

    function wp_verify_nonce( $value, $action ) {
        return $value === 'fixture-nonce';
    }

    function wp_create_nonce( $action ) {
        return 'fixture-nonce';
    }

    function wp_get_session_token() {
        return 'fixture-cookie';
    }

    function register_rest_route( $namespace, $path, $route ) {
        $GLOBALS['routes'][$path] = $route;
    }

    function wp_remote_retrieve_response_code( $response ) {
        return $response['code'];
    }

    function wp_remote_retrieve_body( $response ) {
        return $response['body'];
    }

    function wp_safe_remote_post( $url, $args ) {
        $GLOBALS['requests'][] = [
            $url,
            $args,
        ];
        if ( isset( $GLOBALS['on_http'] ) ) {
            $GLOBALS['on_http']( $url, $args );
        }
        return [
            'code' => $GLOBALS['reply_code'],
            'body' => $GLOBALS['reply_body'],
        ];
    }

    function wp_remote_post( $url, $args ) {
        $GLOBALS['loopbacks'][] = [
            $url,
            $args,
        ];
        return [];
    }

    function esc_url_raw( $url ) {
        return $url;
    }

    function add_query_arg( $args, $url ) {
        return $url . '?' . http_build_query( $args );
    }

    function __( $value, ...$args ) {
        return $value;
    }

    function wp_convert_hr_to_bytes( $value ) {
        return 512 * 1024 * 1024;
    }

    function wp_die() {
        /* Our isolated test invokes the real queue handler without terminating PHP. */
    }

    class WP_User
    {
        public $ID;

        function __construct( $id ) {
            $this->ID = $id;
        }
    }
    class WP_Error
    {
        public $code;

        public $data;

        function __construct( $code = '', ...$args ) {
            $this->code = $code;
            $this->data = $args[1] ?? null;
        }
    }
    class WP_REST_Response
    {
        public $data;

        public $headers = [];

        function __construct( $data ) {
            $this->data = $data;
        }

        function header( $key, $value ) {
            $this->headers[$key] = $value;
        }
    }
    class WP_REST_Request
    {
        public $params;

        public $headers;

        function __construct( $params = [], $headers = [] ) {
            $this->params  = $params;
            $this->headers = $headers;
        }

        function get_json_params() {
            return $this->params;
        }

        function get_body() {
            return json_encode( $this->params );
        }

        function get_header( $name ) {
            return $this->headers[$name] ?? '';
        }
    }
    class WP_Session_Tokens
    {
        static function get_instance( $id ) {
            return new self();
        }

        function get( $token ) {
            return $GLOBALS['cookie_session'] ?? [
                'login'      => time() - 100,
                'expiration' => time() + 3600,
            ];
        }
    }
    class NotificationTestDatabase
    {
        public $prefix = '';

        public $base_prefix = '';

        public $users = 'users';

        public $usermeta = 'usermeta';

        public $options = 'options';

        public $db;

        public $fail = '';

        public $last_error = '';

        public $insert_id = 0;

        public $last_query = '';

        private $last_id = 0;

        function __construct() {
            $this->db = new \PDO( 'sqlite::memory:' );
            $this->db->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
            $this->db->exec( "CREATE TABLE craf_appna_event_bindings(source_id TEXT PRIMARY KEY,app_id TEXT,payload TEXT,storage_version INTEGER DEFAULT 1);\n            CREATE TABLE craf_appna_event_sequences(entity_type TEXT,entity_id INTEGER,version INTEGER,PRIMARY KEY(entity_type,entity_id));\n            CREATE TABLE options(option_id INTEGER PRIMARY KEY AUTOINCREMENT,option_name TEXT UNIQUE,option_value TEXT,autoload TEXT DEFAULT 'no');\n            CREATE TABLE usermeta(umeta_id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER,meta_key TEXT,meta_value TEXT,UNIQUE(user_id,meta_key));\n            CREATE TABLE users(ID INTEGER PRIMARY KEY,display_name TEXT,user_email TEXT);\n            INSERT INTO users VALUES(7,'Fixture Customer','fixture@example.test');" );
        }

        function prepare( $sql, ...$args ) {
            if ( isset( $args[0] ) && is_array( $args[0] ) ) {
                $args = $args[0];
            }
            $i = 0;
            return preg_replace_callback(
                '/%[sdi]/', function ( $m ) use ( &$i, $args ) {
                    $v = $args[$i++];
                    return $m[0] === '%d' ? (string) (int) $v : ( $m[0] === '%i' ? '`' . str_replace( '`', '``', $v ) . '`' : $this->db->quote( (string) $v ) );
                }, $sql
            );
        }

        function suppress_errors( $value ) {
            return false;
        }

        function get_charset_collate() {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        function query( $sql ) {
            $this->last_error = '';
            $this->last_query = $sql;
            if ( isset( $GLOBALS['before_query'] ) ) {
                $GLOBALS['before_query']( $sql );
            }
            if ( $this->fail && strpos( $sql, $this->fail ) !== false ) {
                $this->last_error = 'synthetic database failure';
                return false;
            }
            if ( preg_match( "/INSERT INTO `?([a-z_]+)`? \\(entity_type,entity_id,version\\) VALUES \\('([^']+)',([0-9]+),LAST_INSERT_ID\\(([0-9]+)\\)\\)/", $sql, $m ) ) {
                $old           = $this->db->query( "SELECT version FROM {$m[1]} WHERE entity_type='{$m[2]}' AND entity_id={$m[3]}" )->fetchColumn();
                $this->last_id = $old === false ? (int) $m[4] : max( (int) $old + 1, (int) $m[4] );
                return $this->db->exec( "INSERT INTO {$m[1]} VALUES('{$m[2]}',{$m[3]},{$this->last_id}) ON CONFLICT(entity_type,entity_id) DO UPDATE SET version=excluded.version" );
            }
            // MySQL's binary comparison is byte-sensitive; SQLite text equality is already case-sensitive.
            $sql = str_replace( 'BINARY ', '', $sql );
            // SQLite needs a single-table spelling for MySQL's DELETE alias FROM form.
            $sql = preg_replace( '/^delete `[^`]+` from (`[^`]+`)/i', 'DELETE FROM $1', $sql );
            return $this->db->exec( str_replace( 'INSERT IGNORE', 'INSERT OR IGNORE', $sql ) );
        }

        private function failed_read( $sql ): bool {
            if ( $this->fail && stripos( $sql, $this->fail ) !== false ) {
                $this->last_error = 'synthetic read failure';
                $this->last_query = $sql;
                return true;
            }
            return false;
        }

        function get_var( $sql ) {
            if ( preg_match( '/^SELECT (DATABASE|GET_LOCK|RELEASE_LOCK|IS_USED_LOCK)\(/', $sql ) ) {
                $this->last_error = '';
            }
            if ( $this->failed_read( $sql ) ) {
                return null;
            }
            // Only a protocol stub: real lock exclusion is tested with two MySQL connections.
            if ( $sql === 'SELECT DATABASE()' ) {
                return 'notification_fixture';
            }
            if ( preg_match( '/^SELECT (GET_LOCK|RELEASE_LOCK|IS_USED_LOCK)\(/', $sql ) ) {
                return 1;
            }
            if ( $sql === 'SELECT LAST_INSERT_ID()' ) {
                return $this->last_id;
            }
            $v = $this->db->query( $sql )->fetchColumn();
            return $v === false ? null : $v;
        }

        function get_results( $sql, $mode = null ) {
            if ( substr( $sql, -11 ) === ' FOR UPDATE' ) {
                $this->last_error = '';
            }
            if ( $this->failed_read( $sql ) ) {
                return [];
            }
            $sql = preg_replace( '/ FOR UPDATE$/', '', $sql );
            return $this->db->query( $sql )->fetchAll( $mode === ARRAY_A ? \PDO::FETCH_ASSOC : \PDO::FETCH_OBJ );
        }

        function get_row( $sql, $mode ) {
            return $this->db->query( $sql )->fetch( \PDO::FETCH_ASSOC );
        }

        function get_col( $sql ) {
            return $this->db->query( $sql )->fetchAll( \PDO::FETCH_COLUMN );
        }

        function get_blog_prefix() {
            return '';
        }

        function esc_like( $value ) {
            return $value;
        }

        function delete( $table, $where, ...$args ) {
            $clauses = [];
            foreach ( $where as $key => $value ) {
                $clauses[] = $key . '=' . $this->db->quote( $value );
            }
            return $this->query( 'DELETE FROM ' . $table . ' WHERE ' . implode( ' AND ', $clauses ) );
        }
    }
    $wpdb = new NotificationTestDatabase();
    $base = dirname( __DIR__, 2 );
    require $base . '/vendor/vendor-src/deliciousbrains/wp-background-processing/classes/wp-async-request.php';
    require $base . '/vendor/vendor-src/deliciousbrains/wp-background-processing/classes/wp-background-process.php';
    require $base . '/vendor/vendor-src/wpmvc/queue/src/Sequence.php';
    require $base . '/app/Jobs/NotificationQueue.php';
    require $base . '/app/Support/NotificationEvents.php';
    require $base . '/app/Support/NotificationFormEvents.php';
    require $base . '/app/Support/Auth.php';
    require $base . '/app/Providers/NotificationEventServiceProvider.php';

    function fixture_binding( $source = 'source', $app = 'app' ) {
        $keys = [];
        foreach ( [
            'events',
            'identity',
            'subjects',
        ] as $scope ) {
            $keys[$scope] = [
                'id'     => $scope,
                'secret' => str_repeat( 's', 64 ),
            ];
        }
        return [
            'sourceId'     => $source,
            'appId'        => $app,
            'generation'   => 1,
            'enabled'      => true,
            'provisioning' => 'ready',
            'endpoint'     => 'https://backend.example.test/api/notification-sources',
            'keys'         => $keys,
        ];
    }

    function queued_items( $source = 'source', $channel = 'events' ) {
        $items = [];
        foreach ( \Crafium\AppNatively\App\Support\NotificationEvents::queue( $source, $channel )->get_batches() as $batch ) {
            $items = array_merge( $items, $batch->data );
        }
        foreach ( $items as &$item ) {
            $item['event'] = json_decode( $item['event'], true );
        }
        unset( $item );
        return $items;
    }

    function run_queue( $source = 'source', $channel = 'events' ) {
        $q      = \Crafium\AppNatively\App\Support\NotificationEvents::queue( $source, $channel );
        $method = new \ReflectionMethod( $q, 'handle' );
        $method->setAccessible( true );
        if ( $q->is_queued() && ! $q->is_paused() ) {
            $method->invoke( $q );
        }
    }
}
