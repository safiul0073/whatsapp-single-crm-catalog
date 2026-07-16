<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;

it('renders the icon picker component with the selected icon value', function (): void {
    view()->share('errors', new ViewErrorBag);

    $html = Blade::render('<x-forms.icon-picker name="icon" value="ph-code" label="Icon" />');

    expect($html)->toContain('data-icon-picker');
    expect($html)->toContain('ph-code');
    expect($html)->toContain('Choose an icon');
});
