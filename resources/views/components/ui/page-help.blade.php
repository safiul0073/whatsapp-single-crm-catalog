@props(['pageTitle' => __('This page')])

@php
    $guides = [
        [
            'patterns' => ['user.dashboard'],
            'title' => __('Dashboard guide'),
            'summary' => __('Review workspace performance, channel health, recent conversations, and the actions that need attention.'),
            'steps' => [
                __('Check the WhatsApp connection and reconnect it before sending messages if it is unavailable.'),
                __('Review message, conversation, contact, campaign, and AI usage for the selected date range.'),
                __('Use Quick actions to start a campaign, create a template, import contacts, or build a chatbot.'),
            ],
            'tip' => __('Start here each day to identify channel problems and customer conversations that require follow-up.'),
            'icon' => 'ph-gauge',
        ],
        [
            'patterns' => ['user.inbox.*'],
            'title' => __('Inbox guide'),
            'summary' => __('Handle customer conversations, review contact context, and turn important WhatsApp replies into CRM follow-up.'),
            'steps' => [
                __('Select a conversation, read its history, and send a reply through the connected channel.'),
                __('Use the CRM profile button to create a lead, add notes or tasks, move stages, and assign an agent.'),
                __('Resolve completed conversations so the team can focus on customers who still need attention.'),
            ],
            'tip' => __('Use an approved WhatsApp template when the customer service window is no longer open.'),
            'icon' => 'ph-chats-circle',
        ],
        [
            'patterns' => ['user.contacts.*'],
            'title' => __('Contacts guide'),
            'summary' => __('Maintain clean customer records so conversations, campaigns, CRM leads, and automation use the right people.'),
            'steps' => [
                __('Add contacts manually or import a CSV with an international phone number for each WhatsApp contact.'),
                __('Use tags for customer attributes and groups for reusable campaign audiences.'),
                __('Open a contact to update consent, details, custom fields, tags, groups, and assignment.'),
            ],
            'tip' => __('Keep one contact per person; the application safely updates matching phone numbers inside the current workspace.'),
            'icon' => 'ph-address-book',
        ],
        [
            'patterns' => ['user.groups.*', 'user.tags.*', 'user.segments.*'],
            'title' => __('Audience organization guide'),
            'summary' => __('Organize contacts into reusable tags, groups, and filtered audiences for campaigns and automation.'),
            'steps' => [
                __('Create clear names that the whole team will understand.'),
                __('Attach tags for attributes or behavior and use groups for campaign membership.'),
                __('Preview the matching contacts before using an audience in a campaign.'),
            ],
            'tip' => __('Use consistent labels such as VIP, Campaign Replied, Interested, or Existing Customer.'),
            'icon' => 'ph-users-three',
        ],
        [
            'patterns' => ['user.crm.*'],
            'title' => __('CRM guide'),
            'summary' => __('Turn WhatsApp conversations into owned sales opportunities with stages, assignments, notes, and follow-up tasks.'),
            'steps' => [
                __('Choose a pipeline and review open leads in the Kanban stages.'),
                __('Drag a lead to its next stage as the sales conversation progresses.'),
                __('Assign an agent, record notes, and create a dated task so the next follow-up is never missed.'),
                __('Mark the opportunity won or lost when the sales process finishes.'),
            ],
            'tip' => __('The Inbox manages messages; CRM manages the business process and responsibility around those messages.'),
            'icon' => 'ph-kanban',
        ],
        [
            'patterns' => ['user.leads.*'],
            'title' => __('Prospect leads guide'),
            'summary' => __('Collect or generate potential prospects, verify their contact details, and convert suitable prospects into contacts.'),
            'steps' => [
                __('Generate or add prospects and use filters to find the most relevant records.'),
                __('Review contactability and update the prospect before sending a message.'),
                __('Convert selected prospects into Contacts when they are ready for messaging and CRM follow-up.'),
            ],
            'tip' => __('This prospect list is separate from CRM opportunities. Convert a prospect to a contact before managing the sales process in CRM.'),
            'icon' => 'ph-user-focus',
        ],
        [
            'patterns' => ['user.campaigns.*'],
            'title' => __('Campaign guide'),
            'summary' => __('Send approved messages to a defined audience and measure delivery, replies, failures, and CRM results.'),
            'steps' => [
                __('Choose a connected channel, approved template or message, and a workspace-scoped audience.'),
                __('Preview recipients and resolve missing consent, channel, or contact information before scheduling.'),
                __('Enable CRM lead creation when campaign replies should become sales opportunities.'),
                __('Open the campaign report to review delivery and reply performance.'),
            ],
            'tip' => __('Only message customers who have provided the consent required for the selected communication channel.'),
            'icon' => 'ph-megaphone',
        ],
        [
            'patterns' => ['user.message-templates.*'],
            'title' => __('Message template guide'),
            'summary' => __('Create reusable WhatsApp templates, provide realistic examples, and submit them to Meta for approval.'),
            'steps' => [
                __('Choose the correct category and write a clear body with valid placeholder variables.'),
                __('Add optional headers and buttons, then provide examples for every variable.'),
                __('Submit the template and wait for approval before using it outside the service window or in campaigns.'),
            ],
            'tip' => __('Templates are reviewed by Meta; avoid misleading content and never start or end the body with a variable.'),
            'icon' => 'ph-note',
        ],
        [
            'patterns' => ['user.automations.*'],
            'title' => __('Automation guide'),
            'summary' => __('Build event-driven workflows that respond to customer behavior and update messaging or CRM records automatically.'),
            'steps' => [
                __('Start with one clear trigger such as a received message, keyword, campaign reply, or contact tag.'),
                __('Add conditions when only some contacts should continue through a branch.'),
                __('Add actions such as sending WhatsApp messages, creating CRM leads, assigning agents, adding tags, or creating tasks.'),
                __('Test the flow, save it, and activate it only after confirming every branch.'),
            ],
            'tip' => __('Keep the first version small and review automation runs before adding more branches or delays.'),
            'icon' => 'ph-flow-arrow',
        ],
        [
            'patterns' => ['user.auto-replies.*'],
            'title' => __('Auto-reply guide'),
            'summary' => __('Create simple keyword-based responses for common questions without building a full automation flow.'),
            'steps' => [
                __('Choose the channel and define the keyword or matching rule.'),
                __('Write the response customers should receive when the rule matches.'),
                __('Enable the rule and test it from a real customer conversation.'),
            ],
            'tip' => __('Use Automations instead when the workflow needs conditions, assignments, CRM actions, or multiple steps.'),
            'icon' => 'ph-arrow-bend-up-left',
        ],
        [
            'patterns' => ['user.chatbots.*', 'user.knowledge-bases.*'],
            'title' => __('AI assistant guide'),
            'summary' => __('Give an AI chatbot trusted knowledge and clear behavior before allowing it to answer customer conversations.'),
            'steps' => [
                __('Create a knowledge base and add accurate business, product, policy, and support information.'),
                __('Configure the chatbot tone, instructions, escalation rules, and connected channels.'),
                __('Test expected questions and unknown questions before activating the chatbot.'),
            ],
            'tip' => __('Review and update source information regularly so the chatbot does not rely on outdated business details.'),
            'icon' => 'ph-robot',
        ],
        [
            'patterns' => ['user.whatsapp-cloud.*', 'user.email.*', 'user.sms.*', 'user.telegram.*'],
            'title' => __('Channel setup guide'),
            'summary' => __('Connect and test the communication provider before using Inbox, Campaigns, Templates, or Automations.'),
            'steps' => [
                __('Enter the provider credentials and sender information for this workspace.'),
                __('Save the configuration and run the available connection test.'),
                __('For inbound messaging, confirm the webhook is configured and can receive provider events.'),
            ],
            'tip' => __('Store production credentials securely and rotate them immediately if they are exposed or revoked.'),
            'icon' => 'ph-plugs-connected',
        ],
        [
            'patterns' => ['user.commerce.*'],
            'title' => __('WhatsApp commerce guide'),
            'summary' => __('Prepare products, synchronize the Meta catalog, send product selections in Inbox, and process customer order requests.'),
            'steps' => [
                __('Complete product details, images, variants, prices, inventory, and publication readiness.'),
                __('Connect and synchronize the Meta catalog before sending product messages.'),
                __('Send products from an active WhatsApp conversation and process submitted carts in Orders.'),
            ],
            'tip' => __('Use the detailed help card on Commerce pages for page-specific catalog, product, and order instructions.'),
            'icon' => 'ph-storefront',
        ],
        [
            'patterns' => ['user.workspaces.*'],
            'title' => __('Workspace and team guide'),
            'summary' => __('Control who can access this workspace, what each role may do, and which agent owns customer work.'),
            'steps' => [
                __('Invite team members using their correct business email address.'),
                __('Assign the smallest role and permissions required for their responsibilities.'),
                __('Review inactive members and pending invitations regularly.'),
            ],
            'tip' => __('Workspace members can only access records from workspaces where they are active members.'),
            'icon' => 'ph-users',
        ],
        [
            'patterns' => ['user.subscription.*'],
            'title' => __('Subscription guide'),
            'summary' => __('Review your current plan, usage limits, renewal information, and available upgrade options.'),
            'steps' => [
                __('Compare current usage with message, channel, team, and AI limits.'),
                __('Choose a plan that supports the expected workspace volume.'),
                __('Complete checkout and confirm the subscription status before using paid services.'),
            ],
            'tip' => __('Upgrade before reaching important limits so campaigns and automated services are not interrupted.'),
            'icon' => 'ph-credit-card',
        ],
        [
            'patterns' => ['user.support-tickets.*'],
            'title' => __('Support guide'),
            'summary' => __('Ask the platform support team for help and keep all troubleshooting details in one ticket.'),
            'steps' => [
                __('Describe what you expected, what happened, and which page or channel was involved.'),
                __('Include safe screenshots or error messages without exposing access tokens or passwords.'),
                __('Reply in the same ticket until the issue is resolved.'),
            ],
            'tip' => __('Never include WhatsApp access tokens, payment credentials, passwords, or private customer data in a ticket.'),
            'icon' => 'ph-lifebuoy',
        ],
        [
            'patterns' => ['user.system-notifications.*'],
            'title' => __('Notifications guide'),
            'summary' => __('Review task reminders, system updates, and other workspace events that may require action.'),
            'steps' => [
                __('Open an unread notification to visit its related record.'),
                __('Complete the requested action, such as a CRM follow-up task.'),
                __('Mark notifications read individually or clear all unread items after review.'),
            ],
            'tip' => __('CRM task reminders are sent once to the assigned team member when the task becomes due.'),
            'icon' => 'ph-bell',
        ],
        [
            'patterns' => ['user.profile.*', 'user.two-factor.*'],
            'title' => __('Account security guide'),
            'summary' => __('Keep your profile, password, active sessions, and two-factor authentication secure and current.'),
            'steps' => [
                __('Update your name, email, avatar, and password when needed.'),
                __('Enable two-factor authentication and store recovery codes in a secure location.'),
                __('Revoke sessions you do not recognize or no longer use.'),
            ],
            'tip' => __('Do not share an account between team members; invite each person to the workspace separately.'),
            'icon' => 'ph-shield-check',
        ],
    ];

    $routeName = request()->route()?->getName() ?? '';
    $guide = collect($guides)->first(
        fn (array $candidate): bool => Illuminate\Support\Str::is($candidate['patterns'], $routeName),
    ) ?? [
        'title' => __(':page guide', ['page' => $pageTitle]),
        'summary' => __('Use this page to review and manage the current workspace information shown below.'),
        'steps' => [
            __('Review the page summary, status indicators, and existing records before making changes.'),
            __('Use the primary action to create or update a record and complete all required fields.'),
            __('Check confirmation messages and validation feedback before continuing to another page.'),
        ],
        'tip' => __('All user-panel information is scoped to the currently selected workspace.'),
        'icon' => 'ph-question',
    ];
@endphp

<section class="mb-6 flex flex-col gap-3 rounded-2xl border border-primary/20 bg-primary/5 p-4 sm:flex-row sm:items-center sm:justify-between" data-page-help-banner data-help-route="{{ $routeName }}">
    <div class="flex min-w-0 items-start gap-3">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary text-neutral-0">
            <i class="ph {{ $guide['icon'] }} text-lg"></i>
        </span>
        <div class="min-w-0">
            <p class="text-xs font-bold tracking-wide text-primary uppercase">{{ __('What to do here') }}</p>
            <p class="mt-1 text-sm leading-6 text-title">{{ $guide['summary'] }}</p>
        </div>
    </div>
    <button type="button" class="btn-sm btn-outline shrink-0 justify-center" data-modal-open="userPageHelp">
        <i class="ph ph-question text-base"></i>
        {{ __('Show steps') }}
    </button>
</section>

@push('modals')
    <div class="modal" id="userPageHelp" data-modal>
        <div class="modal__backdrop" data-modal-close></div>
        <div class="modal__panel max-w-2xl" role="dialog" aria-modal="true" aria-labelledby="userPageHelpTitle">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                        <i class="ph {{ $guide['icon'] }} text-xl"></i>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-primary">{{ __('Page help') }}</p>
                        <h2 id="userPageHelpTitle" class="heading-4 text-title">{{ $guide['title'] }}</h2>
                    </div>
                </div>
                <button type="button" class="row-action" data-modal-close aria-label="{{ __('Close help') }}">
                    <i class="ph ph-x text-lg"></i>
                </button>
            </div>

            <p class="mt-4 text-sm leading-6 text-body">{{ $guide['summary'] }}</p>

            <ol class="mt-5 space-y-3">
                @foreach ($guide['steps'] as $step)
                    <li class="flex items-start gap-3">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-primary text-xs font-bold text-neutral-0">{{ $loop->iteration }}</span>
                        <p class="pt-0.5 text-sm leading-6 text-title">{{ $step }}</p>
                    </li>
                @endforeach
            </ol>

            <div class="mt-5 flex items-start gap-3 rounded-xl bg-warning/10 p-4">
                <i class="ph ph-lightbulb mt-0.5 text-warning"></i>
                <div>
                    <p class="text-sm font-semibold text-title">{{ __('Helpful tip') }}</p>
                    <p class="mt-1 text-sm leading-6 text-body">{{ $guide['tip'] }}</p>
                </div>
            </div>

            <div class="mt-6 flex justify-end border-t border-border-soft pt-5">
                <button type="button" class="btn btn-primary" data-modal-close>{{ __('Got it') }}</button>
            </div>
        </div>
    </div>
@endpush
