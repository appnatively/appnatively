<?php

namespace Crafium\AppNatively\App\Support;

defined( 'ABSPATH' ) || exit;

/** A temporary read/write failure must never look like an absent subject. */
class NotificationStorageException extends \RuntimeException {
}
