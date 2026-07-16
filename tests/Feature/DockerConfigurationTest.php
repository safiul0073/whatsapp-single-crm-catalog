<?php

it('defines the application database migrations and queue worker services', function () {
    $compose = file_get_contents(base_path('compose.yaml'));

    expect($compose)
        ->toContain('mysql:')
        ->toContain('image: mysql:8.4')
        ->toContain('migrate:')
        ->toContain('["php", "artisan", "migrate", "--force"]')
        ->toContain('app:')
        ->toContain('queue:')
        ->toContain('["php", "artisan", "queue:work"')
        ->toContain('QUEUE_CONNECTION: database');
});

it('builds the extensions and frontend assets required by the application', function () {
    $dockerfile = file_get_contents(base_path('Dockerfile'));

    expect($dockerfile)
        ->toContain('FROM php:8.3-cli-bookworm')
        ->toContain('pdo_mysql')
        ->toContain('pcntl')
        ->toContain('npm run build')
        ->toContain('composer install');
});
