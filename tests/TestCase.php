<?php

namespace Tests;

use App\Support\Installer;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests run as an installed app whether or not this checkout has a .env.
        // InstallerTest swaps the real check back in.
        $this->app->instance(Installer::class, new class extends Installer
        {
            public function installed(): bool
            {
                return true;
            }
        });
    }
}
