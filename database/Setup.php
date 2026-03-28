<?php

namespace AppNatively\Database;

defined( 'ABSPATH' ) || exit;

use AppNatively\WpMVC\Database\Schema\Schema;

class Setup {
    public function execute() {
        Schema::create(
            'appnatively_connections', function( $table ) {
                $table->big_increments( 'id' );
                $table->string( 'app_id' );
                $table->index( 'app_id' );
                $table->string( 'app_name' )->nullable();
                $table->string( 'status' )->default( 'disconnected' );
                $table->text( 'token' )->nullable();
                $table->string( 'site_url' )->nullable();
                $table->timestamps();
            }
        );
    }

    public function drop() {
        Schema::drop_if_exists( 'appnatively_connections' );
    }
}