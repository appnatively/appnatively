<?php

defined( 'ABSPATH' ) || exit;

/**
 * No admin-ajax routes are registered: the Studio handshake and every app
 * request go through the REST API instead.
 *
 * This file is intentionally left in place — the routing service provider
 * includes routes/{type}/api.php unconditionally, so removing it would emit a
 * warning on every request.
 */
