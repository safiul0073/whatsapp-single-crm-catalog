<?php

use Illuminate\Support\Facades\Route;

it('keeps meta social providers off the whatsapp channel setup page', function () {
    $view = file_get_contents(app_path('Modules/WhatsAppCloud/Resources/views/user/channel-setup.blade.php'));

    expect($view)
        ->not->toContain('channelSettingsDrawer')
        ->not->toContain('store-generic')
        ->not->toContain('ph-messenger-logo')
        ->not->toContain('ph-instagram-logo')
        ->not->toContain('ph-threads-logo');
});

it('registers the meta social user routes on their own page', function () {
    expect(Route::has('user.meta-social.setup'))->toBeTrue()
        ->and(Route::has('user.meta-social.setup.embedded'))->toBeTrue()
        ->and(Route::has('user.meta-social.setup.disconnect'))->toBeTrue();
});

it('does not render instagram in the public footer', function () {
    $footer = file_get_contents(resource_path('views/frontend/themes/classic/navigation/footer.blade.php'));

    expect($footer)
        ->not->toContain('footer_social_instagram')
        ->not->toContain("__('Instagram')");
});
