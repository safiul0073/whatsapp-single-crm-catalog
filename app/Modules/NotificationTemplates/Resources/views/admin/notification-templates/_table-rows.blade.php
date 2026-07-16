@forelse($notificationTemplates as $template)
<tr>
    <td data-th="{{ __('Name') }}" class="text-sm text-neutral-900">{{ $template->name }}</td>
    <td data-th="{{ __('Slug') }}" class="text-sm text-neutral-400">
        <code class="rounded bg-neutral-100 px-1.5 py-0.5 text-xs dark:bg-neutral-800">{{ $template->slug }}</code>
    </td>
    <td data-th="{{ __('Channels') }}">
        <div class="flex flex-wrap gap-1">
            @foreach($template->channels ?? [] as $channel)
                <x-ui.badge variant="neutral">
                    @switch($channel)
                        @case('email') <i class="ph ph-envelope mr-1"></i> @break
                        @case('sms') <i class="ph ph-chat-text mr-1"></i> @break
                        @case('in_app') <i class="ph ph-bell mr-1"></i> @break
                        @case('web_push') <i class="ph ph-broadcast mr-1"></i> @break
                        @case('mobile_push') <i class="ph ph-device-mobile mr-1"></i> @break
                    @endswitch
                    {{ str_replace('_', ' ', ucfirst($channel)) }}
                </x-ui.badge>
            @endforeach
        </div>
    </td>
    <td data-th="{{ __('Status') }}">
        <div class="flex justify-end lg:justify-start rtl:justify-start">
            <x-ui.badge :variant="$template->is_active ? 'success' : 'danger'">
                {{ $template->is_active ? __('Active') : __('Inactive') }}
            </x-ui.badge>
        </div>
    </td>
    <td data-th="{{ __('Actions') }}" class="text-right">
        <x-tables.actions>
            <x-tables.action icon="pencil-simple" :href="route('admin.notification-templates.edit', $template)" :label="__('Edit')" />
        </x-tables.actions>
    </td>
</tr>
@empty
<tr>
    <td colspan="5" class="py-8 text-center text-neutral-400">{{ __('No notification templates found.') }}</td>
</tr>
@endforelse
