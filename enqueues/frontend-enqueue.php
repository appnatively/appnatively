<?php

defined( 'ABSPATH' ) || exit;

// Studio preview is available before notification pairing; the push bridge is enqueued separately.
wp_enqueue_script(
    'craf-appna-website-preview',
    'https://appnatively.com/embed/website-preview.js',
    [],
    null,
    [
        'strategy'  => 'defer',
        'in_footer' => false
    ]
);
