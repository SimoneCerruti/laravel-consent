<?php

declare(strict_types=1);

use SimoneCerruti\Consent\Consent;

it('resolves the singleton', function (): void {
    expect(app(Consent::class))->toBeInstanceOf(Consent::class);
});

it('returns the same instance from the container', function (): void {
    expect(app(Consent::class))->toBe(app(Consent::class));
});

it('merges the package config', function (): void {
    expect(config('consent.placeholder'))->toBe('default');
});

it('loads the package translations', function (): void {
    expect(trans('consent::messages.placeholder'))->toBe('Consent placeholder translation.');
});

it('loads the package views', function (): void {
    expect(view()->exists('consent::placeholder'))->toBeTrue();
});

it('registers the artisan command', function (): void {
    $this->artisan('consent:placeholder')
        ->expectsOutputToContain('Consent placeholder command executed.')
        ->assertSuccessful();
});
