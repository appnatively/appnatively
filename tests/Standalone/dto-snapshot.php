<?php

// phpcs:disable WordPress.Security.EscapeOutput -- CLI-only JSON contract fixture.
if ( PHP_SAPI !== 'cli' ) {
    exit;
}
define( 'ABSPATH', '/unused/' );
$source  = $argv[1] ?? dirname( __DIR__, 2 );
$runtime = dirname( __DIR__, 2 ) . '/vendor/vendor-src/wpmvc/framework/src/';
spl_autoload_register(
    function ( $class ) use ( $source, $runtime ) {
        foreach ( [
            'Crafium\\AppNatively\\App\\DTO\\'   => $source . '/app/DTO/',
            'Crafium\\AppNatively\\WpMVC\\DTO\\' => $runtime . 'DTO/'
        ] as $prefix => $directory ) {
            if ( strpos( $class, $prefix ) === 0 ) {
                require_once $directory . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
                return;
            }
        }
    }
);
$snapshots = [];
$iterator  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source . '/app/DTO', FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
    if ( $file->getExtension() !== 'php' ) {
        continue;
    }
    $relative   = substr( $file->getPathname(), strlen( $source . '/app/DTO/' ), -4 );
    $class      = 'Crafium\\AppNatively\\App\\DTO\\' . str_replace( '/', '\\', $relative );
    $reflection = new ReflectionClass( $class );
    if ( $reflection->isAbstract() ) {
        continue;
    }
    $args = [];
    foreach ( $reflection->getConstructor() ? $reflection->getConstructor()->getParameters() : [] as $param ) {
        $args[] = $param->getType()->getName() === 'array' ? [] : 1;
    }
    $snapshots[$class] = $reflection->newInstanceArgs( $args )->jsonSerialize();
}
ksort( $snapshots );
echo json_encode( $snapshots, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
