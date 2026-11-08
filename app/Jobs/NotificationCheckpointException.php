<?php

namespace Crafium\AppNatively\App\Jobs;

defined( 'ABSPATH' ) || exit;

/** Stop the framework before it can overwrite a failed or superseded checkpoint. */
class NotificationCheckpointException extends \RuntimeException {
}
