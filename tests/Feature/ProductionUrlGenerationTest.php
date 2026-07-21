<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;

it('generates production urls from app url without a public path segment', function (): void {
    config([
        'app.env' => 'production',
        'app.url' => 'https://whatsapp.safiul.pxlaxis.com',
    ]);

    URL::forceRootUrl(null);
    URL::forceScheme(null);

    app()->getProvider(AppServiceProvider::class)->boot();

    expect(url('/'))
        ->toBe('https://whatsapp.safiul.pxlaxis.com')
        ->and(asset('build/assets/app.css'))
        ->toBe('https://whatsapp.safiul.pxlaxis.com/build/assets/app.css')
        ->not->toContain('/public/');
});
