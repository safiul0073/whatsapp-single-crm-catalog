<?php

use App\Services\PhosphorIconCatalog;

it('loads the installed phosphor icon set dynamically from the stylesheet', function (): void {
    if (! file_exists(public_path('vendor/phosphor/regular/style.css'))) {
        $this->markTestSkipped('Phosphor CSS not published — run: php artisan vendor:publish --tag=phosphor');
    }

    $icons = app(PhosphorIconCatalog::class)->all();

    expect($icons)->toContain('ph-acorn');
    expect($icons)->toContain('ph-briefcase');
    expect($icons)->toContain('ph-code');
    expect(count($icons))->toBeGreaterThan(500);
});
