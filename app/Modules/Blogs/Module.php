<?php

namespace App\Modules\Blogs;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'blogs';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'blogs.view' => 'View Blogs',
                'blogs.create' => 'Create Blogs',
                'blogs.edit' => 'Edit Blogs',
                'blogs.delete' => 'Delete Blogs',
                'blog-categories.view' => 'View Blog Categories',
                'blog-categories.create' => 'Create Blog Categories',
                'blog-categories.edit' => 'Edit Blog Categories',
                'blog-categories.delete' => 'Delete Blog Categories',
            ],
        ];
    }

    public function adminNavigation(NavigationBuilder $navigation): void
    {
        $navigation
            ->group('Content')
            ->item('Blogs Management', 'admin.blogs.*', 'ph-newspaper', 'blogs.view', 60, [
                ['label' => 'Blogs', 'route' => 'admin.blogs.index'],
                ['label' => 'Blog Categories', 'route' => 'admin.blog-categories.index'],
            ]);
    }
}
