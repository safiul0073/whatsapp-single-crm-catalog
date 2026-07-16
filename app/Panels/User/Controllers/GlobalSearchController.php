<?php

namespace App\Panels\User\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class GlobalSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2'],
        ]);

        $query = mb_strtolower(trim($data['q']));
        $groups = [];

        foreach ($this->visibleGroups($request) as $group) {
            $results = collect($group['items'])
                ->filter(fn (array $item): bool => str_contains(mb_strtolower($item['search']), $query))
                ->take(8)
                ->map(fn (array $item): array => [
                    'id' => $item['id'],
                    'title' => $item['label'],
                    'subtitle' => $group['label'],
                    'url' => $item['url'],
                ])
                ->values()
                ->all();

            if ($results !== []) {
                $groups[] = [
                    'module' => $group['label'],
                    'icon' => $group['icon'],
                    'results' => $results,
                ];
            }
        }

        return response()->json(['groups' => $groups]);
    }

    protected function visibleGroups(Request $request): array
    {
        $user = $request->user();
        $canSee = function (?string $permission) use ($user): bool {
            if (! $permission) {
                return true;
            }

            $permissions = explode('|', $permission);

            return $user->canAny($permissions);
        };

        return collect($this->linkGroups())
            ->map(function (array $group) use ($canSee): array {
                $group['items'] = collect($group['items'])
                    ->filter(fn (array $item): bool => Route::has($item['route']) && $canSee($item['permission'] ?? null))
                    ->map(function (array $item) use ($group): array {
                        $label = $item['label'];
                        $keywords = $item['keywords'] ?? '';

                        return [
                            'id' => $item['route'],
                            'label' => $label,
                            'url' => route($item['route']),
                            'search' => "{$label} {$group['label']} {$keywords} {$item['route']}",
                        ];
                    })
                    ->values()
                    ->all();

                return $group;
            })
            ->filter(fn (array $group): bool => $group['items'] !== [])
            ->values()
            ->all();
    }

    protected function linkGroups(): array
    {
        return [
            [
                'label' => __('Account'),
                'icon' => 'ph-house',
                'items' => [
                    ['label' => __('Dashboard'), 'route' => 'user.dashboard', 'permission' => 'workspace.view', 'keywords' => 'home overview stats'],
                    ['label' => __('My profile'), 'route' => 'user.profile.edit', 'permission' => null, 'keywords' => 'profile account password security locale timezone'],
                    ['label' => __('Activity Log'), 'route' => 'user.audit-log.index', 'permission' => 'workspace.view', 'keywords' => 'audit history logs activity'],
                    ['label' => __('Media'), 'route' => 'user.media.index', 'permission' => 'workspace.view', 'keywords' => 'library files images uploads'],
                    ['label' => __('Support'), 'route' => 'user.support-tickets.index', 'permission' => 'workspace.view', 'keywords' => 'help tickets issue'],
                    ['label' => __('Notifications'), 'route' => 'user.system-notifications.index', 'permission' => 'workspace.view', 'keywords' => 'alerts messages bell'],
                ],
            ],
            [
                'label' => __('Inbox'),
                'icon' => 'ph-chat-text',
                'items' => [
                    ['label' => __('Inbox'), 'route' => 'user.inbox.index', 'permission' => 'inbox.view|inbox.assigned_only', 'keywords' => 'chat conversations messages replies'],
                    ['label' => __('Channel Setup'), 'route' => 'user.whatsapp-cloud.channel-setup', 'permission' => 'channels.manage', 'keywords' => 'whatsapp cloud api phone waba connect'],
                ],
            ],
            [
                'label' => __('Messaging'),
                'icon' => 'ph-file-text',
                'items' => [
                    ['label' => __('Templates'), 'route' => 'user.message-templates.index', 'permission' => 'templates.manage', 'keywords' => 'message templates whatsapp approval'],
                    ['label' => __('Auto Replies'), 'route' => 'user.auto-replies.index', 'permission' => 'automations.manage', 'keywords' => 'automatic reply autoresponder'],
                ],
            ],
            [
                'label' => __('Contacts'),
                'icon' => 'ph-users-three',
                'items' => [
                    ['label' => __('Contacts'), 'route' => 'user.contacts.index', 'permission' => 'contacts.view', 'keywords' => 'people customers phone import'],
                    ['label' => __('Leads'), 'route' => 'user.leads.index', 'permission' => 'leads.view', 'keywords' => 'prospects captured leads'],
                    ['label' => __('Groups'), 'route' => 'user.groups.index', 'permission' => 'contacts.manage', 'keywords' => 'segments lists contact groups'],
                ],
            ],
            [
                'label' => __('Broadcasting'),
                'icon' => 'ph-paper-plane-tilt',
                'items' => [
                    ['label' => __('Campaigns'), 'route' => 'user.campaigns.index', 'permission' => 'campaigns.view', 'keywords' => 'broadcast send campaign reports'],
                ],
            ],
            [
                'label' => __('Automation & AI'),
                'icon' => 'ph-robot',
                'items' => [
                    ['label' => __('Automations'), 'route' => 'user.automations.index', 'permission' => 'automations.manage', 'keywords' => 'workflow builder trigger flow'],
                    ['label' => __('Chatbots'), 'route' => 'user.chatbots.index', 'permission' => 'chatbots.manage', 'keywords' => 'bot ai assistant'],
                    ['label' => __('Knowledge Bases'), 'route' => 'user.knowledge-bases.index', 'permission' => 'chatbots.manage', 'keywords' => 'knowledge documents training'],
                    ['label' => __('Website Widgets'), 'route' => 'user.chatbots.widgets.index', 'permission' => 'chatbots.manage', 'keywords' => 'widget website embed chat'],
                    ['label' => __('AI Providers'), 'route' => 'user.chatbots.ai-providers.index', 'permission' => 'chatbots.manage', 'keywords' => 'openai provider model keys'],
                ],
            ],
        ];
    }
}
