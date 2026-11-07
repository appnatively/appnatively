<?php

// phpcs:disable WordPress.Security.EscapeOutput -- CLI-only test diagnostics and protocol fixtures are not HTML.

namespace Crafium\AppNatively\WpMVC\RequestValidator {
    class Request
    {
        public function get_param( $name ) {
            return $name === 'field' ? 'synthetic value' : 123;
        }
    }
}
namespace {
    require __DIR__ . '/notification-fixture.php';
    use Crafium\AppNatively\App\Support\NotificationEvents as Events;
    use Crafium\AppNatively\App\Support\NotificationFormEvents as Forms;
    Events::save_binding( fixture_binding() );
    require dirname( __DIR__, 2 ) . '/app/Integrations/Forms/Form.php';
    require dirname( __DIR__, 2 ) . '/app/Integrations/Forms/Forminator.php';
    require dirname( __DIR__, 2 ) . '/app/Integrations/Forms/EverestForms.php';
    class Forminator_API
    {
        static $id = 42;

        static function add_form_entry( ...$args ) {
            return self::$id;
        }
    }
    class Forminator_Base_Form_Model
    {
        static function get_model( $id ) {
            return (object) [
                'id' => $id,
            ];
        }
    }
    class Forminator_Form_Entry_Model
    {
        function __construct( $id ) {
        }
    }
    class Forminator_CForm_Front_Action
    {
        static $prepared_data;
    }
    class Forminator_CForm_Front_Mail
    {
        function process_mail( ...$args ) {
            if ( $GLOBALS['mail_failure'] ) {
                throw new RuntimeException( 'synthetic email failure' );
            }
        }
    }
    $task = new class
    {
        public $id = 43;

        function entry_save( ...$args ) {
            return $this->id;
        }

        function entry_email( ...$args ) {
            if ( $GLOBALS['mail_failure'] ) {
                throw new RuntimeException( 'synthetic email failure' );
            }
        }
    };

    function evf() {
        return (object) [
            'form' => new class
            {
                function get( ...$args ) {
                    return [
                        'id' => 123,
                    ];
                }
            },
            'task' => $GLOBALS['task'],
        ];
    }

    $checks = 0;

    function check( $value, $message ) {
        if ( ! $value ) {
            throw new RuntimeException( $message );
        }
        $GLOBALS['checks']++;
    }

    $cases = [
        [
            'Forminator',
            'forminator',
            [
                'id'     => 123,
                'fields' => [
                    [
                        'type'       => 'text',
                        'element_id' => 'field',
                    ],
                ],
            ],
        ],
        [
            'EverestForms',
            'everest-forms',
            [
                'id'     => 123,
                'fields' => [
                    [
                        'type'     => 'text',
                        'id'       => 'field',
                        'name'     => 'Field',
                        'meta_key' => 'field',
                    ],
                ],
            ],
        ],
    ];
    foreach ( $cases as [$class, $integration, $form] ) {
        Events::queue( 'source', 'events' )->discard();
        $fqcn    = 'Crafium\AppNatively\App\Integrations\Forms\\' . $class;
        $adapter = new $fqcn();
        $submit  = new ReflectionMethod( $adapter, 'submit' );
        $submit->setAccessible( true );
        $mail_failure = true;
        $caught       = false;
        try {
            $submit->invoke( $adapter, new \Crafium\AppNatively\WpMVC\RequestValidator\Request(), $form );
        } catch ( Throwable $error ) {
            $caught = $error->getMessage() === 'synthetic email failure';
        }
        check( $caught, "{$class} preserves native email exception" );
        check( ! queued_items(), "{$class} failed adapter does not invent success" );
        $mail_failure = false;
        $submit->invoke( $adapter, new \Crafium\AppNatively\WpMVC\RequestValidator\Request(), $form );
        $rows = queued_items();
        check( count( $rows ) === 1, "{$class} successful adapter queues directly" );
        check( $rows[0]['event']['data']['entity']['integration'] === $integration, "{$class} routing metadata retained" );
        check( strpos( json_encode( $rows ), 'synthetic value' ) === false, "{$class} excludes submitted field values" );
    }
    echo "PASS: {$checks} actual adapter isolation checks (offline)\n";
}
