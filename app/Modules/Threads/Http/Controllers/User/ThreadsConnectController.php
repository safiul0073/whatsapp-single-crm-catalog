<?php

namespace App\Modules\Threads\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Services\ChannelReadiness\ChannelReadinessService;
use App\Modules\Threads\Services\ThreadsConnectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ThreadsConnectController extends Controller
{
    public function __construct(
        protected ThreadsConnectService $service,
        protected ChannelReadinessService $readiness,
    ) {}

    public function connect(): RedirectResponse
    {
        try {
            return redirect()->away($this->service->authorizationUrl());
        } catch (ValidationException $exception) {
            return $this->back('error', $exception->validator->errors()->first());
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return $this->back('error', __('Threads sign-in was cancelled. Nothing was saved.'));
        }

        $code = (string) $request->query('code', '');
        $state = (string) $request->query('state', '');

        if ($code === '' || $state === '') {
            return $this->back('error', __('Threads did not return a sign-in code.'));
        }

        try {
            $channel = $this->service->connect($request->user(), $code, $state);
        } catch (ValidationException $exception) {
            return $this->back('error', $exception->validator->errors()->first());
        }

        $report = $this->readiness->check($channel);

        return $this->back('status', ($report['status'] ?? 'ready') === 'ready'
            ? __(':channel connected successfully.', ['channel' => $channel->name])
            : __(':channel connected. :headline', ['channel' => $channel->name, 'headline' => $report['headline']]));
    }

    protected function back(string $key, string $message): RedirectResponse
    {
        return redirect()->to(route('user.meta-social.setup').'#threads')->with($key, $message);
    }
}
