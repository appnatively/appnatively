<?php

defined( 'ABSPATH' ) || exit;

/**
 * Unversioned REST routes. Everything lives under /v1 instead — see
 * routes/rest/v1/api.php.
 *
 * This file is intentionally left in place — the routing service provider
 * includes routes/{type}/api.php unconditionally, so removing it would emit a
 * warning on every request.
 */
