<?php

declare(strict_types=1);

namespace SimoneCerruti\Consent\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \SimoneCerruti\Consent\Consent
 */
final class Consent extends Facade {
    protected static function getFacadeAccessor(): string {
        return \SimoneCerruti\Consent\Consent::class;
    }
}
