<?php

defined( 'ABSPATH' ) || exit;

/**
 * No front-end assets are enqueued: this plugin adds REST endpoints only and
 * renders nothing on the site itself.
 *
 * This file is intentionally left in place — the enqueue service provider
 * requires it unconditionally, so removing it would fatal on load.
 */
