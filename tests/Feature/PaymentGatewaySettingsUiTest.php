<?php

it('ports payment gateway tom-select dropdowns outside the settings card', function (): void {
    $view = file_get_contents(base_path('app/Modules/PaymentGatewaySettings/Resources/views/admin/index.blade.php'));
    $script = file_get_contents(resource_path('js/components/tom-select-init.js'));

    expect($view)->toContain('data-dropdown-parent="body"')
        ->and($script)->toContain('dropdownParent')
        ->and($script)->toContain('el.dataset.dropdownParent');
});
