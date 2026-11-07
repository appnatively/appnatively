<?php

defined( 'ABSPATH' ) || exit;

/**
 * The notification provider conditionally enqueues its local WebView bridge
 * after notification consent and pairing.
 *
 * This file is intentionally left in place — the enqueue service provider
 * requires it unconditionally, so removing it would fatal on load.
 */
