<?php

namespace Tests\Unit\Models;

use Tests\TestCase;

class CookieConsentConfigTest extends TestCase
{
    public function test_default_categories_contains_necessary_and_optional()
    {
        $categories = \App\Models\CookieConsentConfig::defaultCategories();

        $this->assertCount(4, $categories);

        $necessary = $categories[0];
        $this->assertEquals('necessary', $necessary['id']);
        $this->assertTrue($necessary['required']);
        $this->assertTrue($necessary['default']);

        $marketing = $categories[3];
        $this->assertEquals('marketing', $marketing['id']);
        $this->assertFalse($marketing['required']);
        $this->assertFalse($marketing['default']);
    }
}
