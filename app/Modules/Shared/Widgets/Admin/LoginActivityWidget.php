<?php

namespace App\Modules\Shared\Widgets\Admin;

use App\Modules\LoginActivity\Models\LoginActivity;
use App\Modules\Shared\Widgets\BaseWidget;

class LoginActivityWidget extends BaseWidget
{
    public function id(): string
    {
        return 'admin-login-activity';
    }

    public function title(): string
    {
        return __('Login Activity');
    }

    public function render(): string
    {
        $loginActivities = LoginActivity::with('user')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return $this->view('widgets.admin.login-activity', compact('loginActivities'));
    }

    public function position(): int
    {
        return 30;
    }

    public function width(): string
    {
        return 'half';
    }
}
