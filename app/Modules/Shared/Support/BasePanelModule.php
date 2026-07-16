<?php

namespace App\Modules\Shared\Support;

abstract class BasePanelModule
{
    abstract public function id(): string;

    /**
     * Return permissions grouped by guard:
     * [
     *   'admin' => ['products.view' => 'View products'],
     *   'web' => ['profile.edit'],
     * ]
     */
    public function permissions(): array
    {
        return [];
    }

    public function policies(): array
    {
        return [];
    }

    public function navigation(string $panel, NavigationBuilder $navigation): void
    {
        $method = $panel.'Navigation';

        if (method_exists($this, $method)) {
            $this->{$method}($navigation);
        }
    }

    public function adminNavigation(NavigationBuilder $navigation): void
    {
        //
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        //
    }
}
