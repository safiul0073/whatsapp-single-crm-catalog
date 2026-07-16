<x-layouts.admin :title="__('Login Activity')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Login Activity') }}</h1>
        </div>

        <div class="section-card">
            <x-forms.filters action="{{ route('admin.login-activity.index') }}" gridClass="grid-cols-1 md:grid-cols-2 lg:grid-cols-5">
                <x-forms.filters.select name="event" label="Event" :options="['login', 'logout', 'failed', 'lockout']" />
                <x-forms.filters.select name="user_type" label="User Type" :options="['App\Models\Admin' => 'Admin', 'App\Models\User' => 'User']" />
                <x-forms.filters.text name="ip_address" label="IP Address" placeholder="127.0.0.1" />
                <x-forms.filters.date name="date_from" label="Date From" />
                <x-forms.filters.date name="date_to" label="Date To" />
            </x-forms.filters>

            <x-tables.table>
                <thead>
                    <tr>
                        <th>{{ __('User') }}</th>
                        <th>{{ __('Event') }}</th>
                        <th>{{ __('IP Address') }}</th>
                        <th>{{ __('Browser') }}</th>
                        <th>{{ __('Platform') }}</th>
                        <th>{{ __('Device') }}</th>
                        <th>{{ __('Date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activities as $activity)
                    <tr>
                        <td data-th="{{ __('User') }}">
                            @if($activity->user)
                                <div>
                                    <p class="text-sm font-medium text-neutral-900">{{ $activity->user->name }}</p>
                                    <p class="text-xs text-neutral-400">{{ $activity->user->email }}</p>
                                </div>
                            @else
                                <span class="text-sm text-neutral-400">
                                    {{ $activity->metadata['email'] ?? __('Unknown') }}
                                </span>
                            @endif
                        </td>
                        <td data-th="{{ __('Event') }}">
                            <div class="flex justify-end lg:justify-start rtl:justify-start">
                                <x-ui.badge :variant="$activity->getEventBadgeVariant()">
                                    {{ ucfirst($activity->event) }}
                                </x-ui.badge>
                            </div>
                        </td>
                        <td data-th="{{ __('IP Address') }}" class="text-sm text-neutral-600">
                            {{ $activity->ip_address ?? __('N/A') }}
                        </td>
                        <td data-th="{{ __('Browser') }}" class="text-sm text-neutral-600">
                            {{ $activity->browser ?? __('Unknown') }}
                        </td>
                        <td data-th="{{ __('Platform') }}" class="text-sm text-neutral-600">
                            {{ $activity->platform ?? __('Unknown') }}
                        </td>
                        <td data-th="{{ __('Device') }}" class="text-sm text-neutral-600">
                            {{ $activity->device ?? __('Unknown') }}
                        </td>
                        <td data-th="{{ __('Date') }}" class="text-sm text-neutral-400">
                            {{ format_date($activity->created_at, true) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-neutral-400">{{ __('No login activity found.') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </x-tables.table>

            <x-tables.pagination :paginator="$activities" />
        </div>
    </div>
</x-layouts.admin>
