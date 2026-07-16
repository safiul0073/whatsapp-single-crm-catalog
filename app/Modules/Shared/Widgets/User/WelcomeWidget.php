<?php

namespace App\Modules\Shared\Widgets\User;

use App\Modules\Shared\Widgets\BaseWidget;

class WelcomeWidget extends BaseWidget
{
    public function id(): string
    {
        return 'user-welcome';
    }

    public function title(): string
    {
        return __('Welcome');
    }

    public function render(): string
    {
        $user = auth()->user();

        return $this->view('widgets.user.welcome', compact('user'));
    }

    public function position(): int
    {
        return 10;
    }

    public function width(): string
    {
        return 'full';
    }

    public function panel(): string
    {
        return 'user';
    }
}
