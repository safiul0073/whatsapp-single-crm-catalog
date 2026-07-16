<?php

namespace App\Modules\Shared\Widgets\User;

use App\Modules\Shared\Widgets\BaseWidget;

class QuickLinksWidget extends BaseWidget
{
    public function id(): string
    {
        return 'user-quick-links';
    }

    public function title(): string
    {
        return __('Quick Links');
    }

    public function render(): string
    {
        return $this->view('widgets.user.quick-links');
    }

    public function position(): int
    {
        return 20;
    }

    public function width(): string
    {
        return 'half';
    }

    public function panel(): string
    {
        return 'user';
    }
}
