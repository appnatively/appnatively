<?php

namespace AppNatively\Tests\Unit;

use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
    /**
     * Test appnatively_version helper.
     */

    /**
     * Test appnatively_now helper.
     */
    public function test_my_plugin_now() {
        $now = appnatively_now();
        $this->assertInstanceOf( 'AppNatively\WpMVC\Helpers\Date', $now );
    }
}
