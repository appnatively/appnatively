<?php

defined( 'ABSPATH' ) || exit;

/**
 * No admin assets are enqueued: the settings screen is rendered server-side
 * from resources/views/settings.php using WordPress' own admin styles.
 *
 * This file is intentionally left in place — the enqueue service provider
 * requires it unconditionally, so removing it would fatal on load.
 */
