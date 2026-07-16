<?php

use Illuminate\Support\Facades\Blade;

it('renders a checked switch with accessible title text', function (): void {
    $html = Blade::render('<x-forms.switch name="is_active" :checked="true" title="Enable rule" />');

    expect($html)
        ->toContain('class="form-switch"')
        ->toContain('name="is_active"')
        ->toContain('class="form-switch__input peer"')
        ->toContain('class="form-switch__track"')
        ->toContain('checked')
        ->toContain('aria-label="Enable rule"')
        ->not->toContain('type="hidden"');
});

it('renders a hidden unchecked fallback only when requested', function (): void {
    $html = Blade::render('<x-forms.switch name="is_active" unchecked-value="0" />');

    expect($html)
        ->toContain('type="hidden"')
        ->toContain('name="is_active"')
        ->toContain('value="0"');
});

it('can submit its parent form when changed', function (): void {
    $html = Blade::render('<x-forms.switch :checked="false" submit-on-change />');

    expect($html)->toContain('onchange="this.form.submit()"');
});

it('renders visible label text when supplied', function (): void {
    $html = Blade::render('<x-forms.switch label="Email digest" />');

    expect($html)
        ->toContain('aria-label="Email digest"')
        ->toContain('class="form-switch__label"')
        ->toContain('Email digest');
});
