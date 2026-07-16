<?php

namespace App\Modules\Shared\Widgets;

use App\Modules\Shared\Contracts\DashboardWidget;

abstract class BaseWidget implements DashboardWidget
{
    public function width(): string
    {
        return 'half';
    }

    public function permission(): ?string
    {
        return null;
    }

    public function panel(): string
    {
        return 'admin';
    }

    public function shouldRender(): bool
    {
        return true;
    }

    public function cacheFor(): ?int
    {
        return null;
    }

    /**
     * Render a Blade view and return the HTML string.
     *
     * @param  array<string, mixed>  $data
     */
    protected function view(string $view, array $data = []): string
    {
        return view($view, $data)->render();
    }
}
