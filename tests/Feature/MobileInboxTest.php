<?php

use App\Modules\Commerce\Http\Controllers\Api\MobileOrderController;
use App\Modules\Inbox\Http\Controllers\Api\MobileInboxController;
use Illuminate\Http\Request;
use Illuminate\Routing\ControllerDispatcher;

it('correctly binds conversation id rather than workspace id on mobile inbox show and store', function (): void {
    $request = Request::create('api/v1/mobile/workspaces/5/inbox/conversations/42');
    $route = Route::getRoutes()->match($request);

    $controller = new MobileInboxController;
    $dispatcher = new ControllerDispatcher(app());
    $reflection = new ReflectionMethod($dispatcher, 'resolveParameters');
    $reflection->setAccessible(true);
    $parameters = $reflection->invoke($dispatcher, $route, $controller, 'show');

    // Verify parameter resolution maps correctly
    expect($parameters['workspace'])->toBe('5')
        ->and($parameters['conversation'])->toBe('42');
});

it('correctly binds order id rather than workspace id on mobile orders show', function (): void {
    $request = Request::create('api/v1/mobile/workspaces/5/orders/99');
    $route = Route::getRoutes()->match($request);

    $controller = new MobileOrderController;
    $dispatcher = new ControllerDispatcher(app());
    $reflection = new ReflectionMethod($dispatcher, 'resolveParameters');
    $reflection->setAccessible(true);
    $parameters = $reflection->invoke($dispatcher, $route, $controller, 'show');

    expect($parameters['workspace'])->toBe('5')
        ->and($parameters['order'])->toBe('99');
});
