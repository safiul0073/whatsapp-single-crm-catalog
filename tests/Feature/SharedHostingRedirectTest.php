<?php

it('redirects direct public paths to canonical app paths', function (): void {
    $this->withServerVariables(['HTTP_HOST' => 'whatsapp.safiul.pxlaxis.com'])
        ->get('/public/login')
        ->assertRedirect('/login');
});

it('redirects duplicated host paths to the homepage', function (): void {
    config(['app.url' => 'https://whatsapp.safiul.pxlaxis.com']);

    $this->withServerVariables(['HTTP_HOST' => 'whatsapp.safiul.pxlaxis.com'])
        ->get('/whatsapp.safiul.pxlaxis.com')
        ->assertRedirect(route('home'));

    $this->withServerVariables(['HTTP_HOST' => 'whatsapp.safiul.pxlaxis.com'])
        ->get('/public/whatsapp.safiul.pxlaxis.com')
        ->assertRedirect(route('home'));
});
