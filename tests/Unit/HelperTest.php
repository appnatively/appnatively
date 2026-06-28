<?php

namespace Crafium\AppNatively\Tests\Unit;

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
        $now = craf_appna_now();
        $this->assertInstanceOf( 'Crafium\AppNatively\WpMVC\Helpers\Date', $now );
    }
}
