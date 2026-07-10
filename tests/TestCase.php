<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUpTraits(): array
    {
        $uses = array_flip(class_uses_recursive(static::class));

        if (isset($uses[Concerns\RefreshDatabase::class])) {
            $this->refreshDatabase();
        }

        return parent::setUpTraits();
    }
}
