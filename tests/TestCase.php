<?php

declare(strict_types=1);

namespace SimoneCerruti\Consent\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SimoneCerruti\Consent\ConsentServiceProvider;

abstract class TestCase extends Orchestra {
    protected function getPackageProviders($app): array {
        return [
            ConsentServiceProvider::class,
        ];
    }
}
