<?php

use App\Modules\Frontend\Database\Seeders\FrontendPageSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendSectionSeeder;
use App\Modules\Frontend\Database\Seeders\FrontendThemeSettingSeeder;
use Database\Seeders\WaProLandingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('loads the contact page', function (): void {
    $this->seed(FrontendThemeSettingSeeder::class);
    $this->seed(FrontendSectionSeeder::class);
    $this->seed(FrontendPageSeeder::class);
    $this->seed(WaProLandingSeeder::class);

    $response = $this->get(route('contact'));

    $response->assertOk();
    $response->assertSee('Talk to the WaPro team');
    $response->assertSee('Send message');
    $response->assertSee('First name');
});

it('contact page is accessible at /contact url', function (): void {
    $this->seed(FrontendThemeSettingSeeder::class);
    $this->seed(FrontendSectionSeeder::class);
    $this->seed(FrontendPageSeeder::class);
    $this->seed(WaProLandingSeeder::class);

    $this->get('/contact')->assertOk();
});

it('stores a contact form submission', function (): void {
    $response = $this->postJson(route('contact.submit'), [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'company' => 'Acme Inc',
        'interest' => 'cloud-api-setup',
        'message' => 'We would like to discuss WhatsApp Cloud API setup.',
    ]);

    $response->assertOk()->assertJson(['success' => true]);
});

it('redirects browser contact form submissions back with a success message', function (): void {
    $response = $this->from(route('contact'))->post(route('contact.submit'), [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'company' => 'Acme Inc',
        'interest' => 'cloud-api-setup',
        'message' => 'We would like to discuss WhatsApp Cloud API setup.',
    ]);

    $response
        ->assertRedirect(route('contact'))
        ->assertSessionHas('contact_success');
});

it('validates required fields on contact form submission', function (): void {
    $this->postJson(route('contact.submit'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'company', 'interest', 'message']);
});

it('validates email format on contact form submission', function (): void {
    $this->postJson(route('contact.submit'), [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'not-an-email',
        'company' => 'Acme Inc',
        'interest' => 'cloud-api-setup',
        'message' => 'Hello, we have a project in mind.',
    ])->assertStatus(422)->assertJsonValidationErrors(['email']);
});
