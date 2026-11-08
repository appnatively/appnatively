<?php

namespace Crafium\AppNatively\App\Support;

defined( 'ABSPATH' ) || exit;

/** A failed authorization read/write must never become an anonymous success. */
class AuthorizationStorageException extends \RuntimeException {
    public function __construct() {
        parent::__construct( 'Authorization storage is temporarily unavailable. Please retry.', 503 );
    }
}
