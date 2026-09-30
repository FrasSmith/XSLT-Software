# Mythic Beasts SFTP deployment

This guide describes a generic SFTP-only deployment. Replace the placeholders with the values for your hosting account.

## Requirements

Configure the site to use PHP 8.3 or newer. Required extensions are:

- DOM
- JSON
- libxml
- mbstring
- XSL
- ZIP

The application also needs a writable system temporary directory and PHP session storage.

## Build locally

Composer does not need to run on the server. Build production dependencies locally:

```bash
composer install --no-dev --classmap-authoritative
```

Run the tests before uploading:

```bash
composer test
```

## Recommended layout

Keep the application outside the public document root:

```text
/home/USERNAME/apps/pptx-checker/
├── resources/
├── src/
├── templates/
├── vendor/
├── composer.json
└── composer.lock

/home/USERNAME/www/DOMAIN/pptx-checker/
├── assets/
├── .htaccess
├── .user.ini
├── deployment.php
└── index.php
```

Upload the contents of local `public/` into the public `pptx-checker/` directory. Do not expose `src/`, `resources/`, `templates/`, or `vendor/` directly.

## Deployment configuration

Copy `public/deployment.php.example` to `deployment.php` and set:

```php
return [
    'applicationRoot' => dirname(__DIR__, 3) . '/apps/pptx-checker',
    'basePath' => '/pptx-checker',
];
```

The `basePath` must match the URL subdirectory. The application root must point to the private directory containing `vendor/`.

## PHP configuration

Upload `public/.user.ini` to the public directory. Adjust limits to the host's permitted values. Do not commit credentials or host-specific deployment files.

## Verification

Test:

1. The upload form and stylesheet
2. A report without PDF generation
3. A report with PDF generation selected
4. Notes included and omitted
5. A presentation containing grouped images
6. A presentation with missing alt text
7. The JSON API, if exposed

If PHP returns HTTP 500, upload `public/diagnose.php.example` temporarily as `diagnose.php`, inspect its output, and remove it immediately after use.
