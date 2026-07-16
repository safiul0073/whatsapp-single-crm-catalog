<?php

use App\Modules\WhatsAppCloud\Services\ChannelSetupService;
use Illuminate\Support\Facades\Route;

it('hides meta social providers from the user channel setup', function () {
    expect(ChannelSetupService::HIDDEN_USER_PROVIDERS)
        ->toBe(['messenger', 'instagram', 'threads']);

    $service = file_get_contents(app_path('Modules/WhatsAppCloud/Services/ChannelSetupService.php'));

    expect($service)
        ->toContain('except(self::HIDDEN_USER_PROVIDERS)')
        ->toContain('in_array($channel->provider, self::HIDDEN_USER_PROVIDERS, true)');
});

it('does not register the meta social user routes', function () {
    expect(Route::has('user.meta-social.setup'))->toBeFalse()
        ->and(Route::has('user.meta-social.setup.embedded'))->toBeFalse()
        ->and(Route::has('user.meta-social.setup.disconnect'))->toBeFalse();
});

it('does not render instagram in the public footer', function () {
    $footer = file_get_contents(resource_path('views/frontend/themes/classic/navigation/footer.blade.php'));

    expect($footer)
        ->not->toContain('footer_social_instagram')
        ->not->toContain("__('Instagram')");
});
