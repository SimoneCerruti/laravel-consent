<div align="center">
    <h1>Laravel Consent</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/simonecerruti/laravel-consent"><img src="https://img.shields.io/packagist/v/simonecerruti/laravel-consent.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/simonecerruti/laravel-consent"><img src="https://img.shields.io/packagist/php-v/simonecerruti/laravel-consent.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/simonecerruti/laravel-consent"><img src="https://badge.laravel.cloud/badge/simonecerruti/laravel-consent?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/simonecerruti/laravel-consent/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/simonecerruti/laravel-consent/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/simonecerruti/laravel-consent"><img src="https://img.shields.io/packagist/dt/simonecerruti/laravel-consent.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Cookie consent banner, consent-gated scripts, proof of consent and GDPR tooling for Laravel

## Installation

You can install the package via Composer:

```bash
composer require simonecerruti/laravel-consent
```

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="consent"
```

Or, you may publish each resource individually:

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag="consent-config"
```

### Publishing and Running the Migrations

```bash
php artisan vendor:publish --tag="consent-migrations"
php artisan migrate
```

### Publishing the Views

```bash
php artisan vendor:publish --tag="consent-views"
```

### Publishing the Translations

```bash
php artisan vendor:publish --tag="consent-lang"
```

### Publishing the Public Assets

```bash
php artisan vendor:publish --tag="consent-assets"
```

## Usage

<!-- Add a basic usage example here. -->

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Laravel Consent! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Simone Cerruti](https://github.com/simonecerruti)
- [All Contributors](../../contributors)

## License

Laravel Consent is open-sourced software licensed under the [MIT license](LICENSE.md).
