<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Тесты не зависят от собранных Vite-ассетов (нет нужды в `npm run build`
        // перед `php artisan test`) — рендер Blade с @vite не требует manifest.json.
        $this->withoutVite();
    }
}
