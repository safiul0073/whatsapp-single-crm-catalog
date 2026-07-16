<?php

namespace App\Modules\Workspaces;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Policies\WorkspacePolicy;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'workspaces';
    }

    public function policies(): array
    {
        return [
            Workspace::class => WorkspacePolicy::class,
        ];
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'workspaces.view' => 'View SaaS clients',
                'workspaces.manage' => 'Manage SaaS clients',
            ],
            'web' => [
                'workspace.view' => 'View workspace',
                'workspace.manage' => 'Manage workspace',
                'workspace.edit' => 'Edit workspace details',
                'team.manage' => 'Manage workspace team members',
                'team.manage.staff_only' => 'Manage Staff team members only',
                'subscription.manage' => 'Manage subscriptions and billing',
                'billing.view' => 'View billing information',
                'channels.manage' => 'Manage messaging channels',
                'contacts.view' => 'View contacts',
                'contacts.manage' => 'Manage contacts',
                'contacts.assigned_only' => 'View and manage assigned contacts only',
                'leads.view' => 'View leads',
                'leads.manage' => 'Manage leads',
                'campaigns.view' => 'View campaigns',
                'campaigns.create' => 'Create campaigns',
                'campaigns.manage' => 'Manage campaigns',
                'templates.manage' => 'Manage message templates',
                'inbox.view' => 'View inbox',
                'inbox.assigned_only' => 'View assigned conversations only',
                'inbox.reply' => 'Reply to conversations',
                'inbox.assign' => 'Assign conversations',
                'reports.view' => 'View reports',
                'automations.manage' => 'Manage automations',
                'chatbots.manage' => 'Manage chatbots',
                'settings.view' => 'View workspace settings',
                'settings.edit' => 'Edit workspace settings',
            ],
        ];
    }

    public function adminNavigation(NavigationBuilder $navigation): void
    {
        //
    }

    public function userNavigation(NavigationBuilder $navigation): void {}
}
