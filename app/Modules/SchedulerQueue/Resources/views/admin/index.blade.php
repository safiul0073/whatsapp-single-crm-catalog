<x-layouts.admin :title="__('Scheduler & Queues')">
    <div class="space-y-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="heading-4 text-neutral-950">{{ __('Scheduler & Queues') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Manage approved scheduled tasks and inspect the database queue.') }}</p>
            </div>

            @can('scheduler-queues.manage')
                <form method="POST" action="{{ route('admin.scheduler-queues.workers.restart') }}">
                    @csrf
                    <x-ui.button type="submit" variant="outline" class="w-full justify-center gap-2 lg:w-auto">
                        <i class="ph ph-arrows-clockwise"></i>
                        {{ __('Restart Workers') }}
                    </x-ui.button>
                </form>
            @endcan
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            @foreach ([
                ['label' => __('Pending Jobs'), 'value' => $summary['pending'], 'icon' => 'ph-clock', 'tone' => 'bg-primary/10 text-primary'],
                ['label' => __('Reserved Jobs'), 'value' => $summary['reserved'], 'icon' => 'ph-lock-key', 'tone' => 'bg-warning/10 text-warning'],
                ['label' => __('Failed Jobs'), 'value' => $summary['failed'], 'icon' => 'ph-warning-circle', 'tone' => 'bg-error/10 text-error'],
            ] as $card)
                <div class="section-card p-5">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm text-neutral-500">{{ $card['label'] }}</p>
                            <p class="mt-2 text-2xl font-bold text-neutral-950">{{ number_format($card['value']) }}</p>
                        </div>
                        <div class="flex h-11 w-11 items-center justify-center rounded-lg {{ $card['tone'] }}">
                            <i class="ph {{ $card['icon'] }} text-xl"></i>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="section-card overflow-hidden">
            <div class="flex gap-2 overflow-x-auto border-b border-neutral-100 px-4 pt-4">
                @foreach ([
                    'scheduler' => __('Scheduler'),
                    'pending' => __('Pending Queue'),
                    'failed' => __('Failed Jobs'),
                ] as $tab => $label)
                    <a href="{{ route('admin.scheduler-queues.index', ['tab' => $tab]) }}#{{ $tab }}"
                        class="shrink-0 border-b-2 px-3 pb-3 text-sm font-medium transition-colors {{ $activeTab === $tab ? 'border-primary text-primary' : 'border-transparent text-neutral-500 hover:text-neutral-700' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <section id="scheduler" class="{{ $activeTab === 'scheduler' ? '' : 'hidden' }}">
                <div class="border-b border-neutral-100 p-4">
                    <h2 class="text-base font-semibold text-neutral-950">{{ __('Managed Scheduler Entries') }}</h2>
                    <p class="mt-1 text-sm text-neutral-500">{{ __('Only approved registry entries can be edited or run from this screen.') }}</p>
                </div>

                <x-tables.table>
                    <thead>
                        <tr>
                            <th>{{ __('Entry') }}</th>
                            <th>{{ __('Frequency') }}</th>
                            <th>{{ __('Queue') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Last Run') }}</th>
                            <th class="text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td data-th="{{ __('Entry') }}">
                                    <div class="text-right lg:text-left">
                                        <p class="text-sm font-bold text-neutral-950">{{ $entry->label }}</p>
                                        <p class="text-xs text-neutral-500">{{ $entry->key }}</p>
                                        <div class="mt-2 flex flex-wrap justify-end gap-1.5 lg:justify-start">
                                            <x-ui.badge variant="neutral">{{ $entry->type }}</x-ui.badge>
                                            <x-ui.badge variant="info">{{ class_basename($entry->target) }}</x-ui.badge>
                                        </div>
                                    </div>
                                </td>
                                <td data-th="{{ __('Frequency') }}">
                                    @can('scheduler-queues.edit')
                                        <select form="scheduler-entry-form-{{ $entry->id }}" name="frequency" class="select-field min-w-[10rem] text-sm">
                                            @foreach ($frequencies as $frequency => $label)
                                                <option value="{{ $frequency }}" @selected($entry->frequency === $frequency)>{{ __($label) }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="text-sm text-neutral-700">{{ __($frequencies[$entry->frequency] ?? $entry->frequency) }}</span>
                                    @endcan
                                </td>
                                <td data-th="{{ __('Queue') }}">
                                    @can('scheduler-queues.edit')
                                        <input form="scheduler-entry-form-{{ $entry->id }}" name="queue" value="{{ $entry->queue }}" class="form-input h-10 min-w-[8rem] text-sm">
                                    @else
                                        <span class="text-sm text-neutral-700">{{ $entry->queue }}</span>
                                    @endcan
                                </td>
                                <td data-th="{{ __('Status') }}">
                                    <div class="flex flex-col items-end gap-2 lg:items-start">
                                        @can('scheduler-queues.edit')
                                            <label class="inline-flex items-center gap-2 whitespace-nowrap text-sm text-neutral-700">
                                                <x-forms.switch
                                                    form="scheduler-entry-form-{{ $entry->id }}"
                                                    name="enabled"
                                                    :checked="$entry->enabled"
                                                    uncheckedValue="0"
                                                    title="{{ __('Toggle scheduler entry') }}"
                                                />
                                                <span>{{ $entry->enabled ? __('Enabled') : __('Disabled') }}</span>
                                            </label>
                                        @else
                                            <x-ui.badge :variant="$entry->enabled ? 'success' : 'neutral'">
                                                {{ $entry->enabled ? __('Enabled') : __('Disabled') }}
                                            </x-ui.badge>
                                        @endcan

                                        @if ($entry->last_status)
                                            <x-ui.badge :variant="$entry->last_status === 'failed' ? 'danger' : ($entry->last_status === 'success' ? 'success' : 'warning')">
                                                {{ ucfirst($entry->last_status) }}
                                            </x-ui.badge>
                                        @endif
                                    </div>
                                </td>
                                <td data-th="{{ __('Last Run') }}">
                                    <div class="text-right lg:text-left">
                                        <p class="text-sm text-neutral-700">{{ $entry->last_run_at?->format('M d, Y H:i') ?? __('Never') }}</p>
                                        @if ($entry->last_message)
                                            <p class="mt-1 max-w-xs text-xs text-neutral-500">{{ $entry->last_message }}</p>
                                        @endif
                                    </div>
                                </td>
                                <td data-th="{{ __('Actions') }}" class="text-right">
                                    @canany(['scheduler-queues.edit', 'scheduler-queues.run'])
                                        @can('scheduler-queues.edit')
                                            <form id="scheduler-entry-form-{{ $entry->id }}" method="POST" action="{{ route('admin.scheduler-queues.update', $entry) }}">
                                                @csrf
                                                @method('PUT')
                                            </form>
                                        @endcan

                                        <div class="relative inline-flex justify-end">
                                            <button type="button" class="btn-icon h-9 w-9" data-floating-dropdown="scheduler-entry-actions-{{ $entry->id }}" aria-label="{{ __('Row actions') }}">
                                                <i class="ph-bold ph-dots-three"></i>
                                            </button>
                                            <div id="scheduler-entry-actions-{{ $entry->id }}" class="floating-dropdown-panel min-w-44">
                                                @can('scheduler-queues.edit')
                                                    <button type="submit" form="scheduler-entry-form-{{ $entry->id }}" class="floating-dropdown-item w-full text-left">
                                                        {{ __('Save changes') }}
                                                    </button>
                                                @endcan
                                                @can('scheduler-queues.run')
                                                    <form method="POST" action="{{ route('admin.scheduler-queues.run', $entry) }}">
                                                        @csrf
                                                        <button type="submit" class="floating-dropdown-item w-full text-left">
                                                            {{ __('Run now') }}
                                                        </button>
                                                    </form>
                                                @endcan
                                            </div>
                                        </div>
                                    @endcanany
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="table-empty-state">{{ __('No scheduler entries found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-tables.table>
            </section>

            <section id="pending" class="{{ $activeTab === 'pending' ? '' : 'hidden' }}">
                <div class="border-b border-neutral-100 p-4">
                    <div class="rounded-2xl border border-neutral-100 bg-neutral-50/70 p-4">
                        <form method="GET" action="{{ route('admin.scheduler-queues.index') }}" class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] xl:items-end">
                        <input type="hidden" name="tab" value="pending">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-neutral-500">{{ __('Queue') }}</label>
                            <select name="queue" class="select-field bg-white">
                                <option value="">{{ __('All queues') }}</option>
                                @foreach ($queueNames as $queueName)
                                    <option value="{{ $queueName }}" @selected(($filters['queue'] ?? '') === $queueName)>{{ $queueName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-neutral-500">{{ __('Status') }}</label>
                            <select name="status" class="select-field bg-white">
                                <option value="">{{ __('All statuses') }}</option>
                                <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>{{ __('Pending') }}</option>
                                <option value="reserved" @selected(($filters['status'] ?? '') === 'reserved')>{{ __('Reserved') }}</option>
                            </select>
                        </div>
                        <x-ui.button type="submit" variant="outline" class="justify-center gap-2 xl:min-w-32">
                            <i class="ph ph-funnel-simple"></i>
                            {{ __('Filter') }}
                        </x-ui.button>
                        </form>
                    </div>
                </div>

                <x-tables.table>
                    <thead>
                        <tr>
                            <th>{{ __('Job') }}</th>
                            <th>{{ __('Queue') }}</th>
                            <th>{{ __('Attempts') }}</th>
                            <th>{{ __('Available') }}</th>
                            <th>{{ __('Reserved') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pendingJobs as $job)
                            <tr>
                                <td data-th="{{ __('Job') }}">
                                    <div class="text-right lg:text-left">
                                        <p class="text-sm font-bold text-neutral-950">{{ $job['display_name'] }}</p>
                                        <p class="max-w-xl break-all text-xs text-neutral-500">{{ $job['job_class'] }}</p>
                                    </div>
                                </td>
                                <td data-th="{{ __('Queue') }}"><x-ui.badge variant="neutral">{{ $job['queue'] }}</x-ui.badge></td>
                                <td data-th="{{ __('Attempts') }}" class="text-sm text-neutral-700">{{ $job['attempts'] }}</td>
                                <td data-th="{{ __('Available') }}" class="text-sm text-neutral-500">{{ $job['available_at'] ?? __('Now') }}</td>
                                <td data-th="{{ __('Reserved') }}" class="text-sm text-neutral-500">{{ $job['reserved_at'] ?? __('Not reserved') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12">
                                    <div class="flex flex-col items-center justify-center rounded-3xl border border-dashed border-neutral-200 bg-neutral-50/80 px-6 py-12 text-center">
                                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-neutral-400 shadow-sm">
                                            <i class="ph ph-clock-countdown text-2xl"></i>
                                        </div>
                                        <p class="mt-4 text-base font-medium text-neutral-700">{{ __('No pending jobs found.') }}</p>
                                        <p class="mt-1 max-w-md text-sm text-neutral-500">{{ __('When jobs are waiting in the database queue, they will appear here with their queue name, attempts, and availability timing.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-tables.table>

                <div class="flex flex-col gap-4 border-t border-neutral-100 p-4 lg:flex-row lg:items-center lg:justify-between">
                    <x-tables.pagination :paginator="$pendingJobs" />
                    @can('scheduler-queues.manage')
                        <form method="POST" action="{{ route('admin.scheduler-queues.pending.clear') }}" class="w-full rounded-2xl bg-gradient-to-r from-error/5 via-white to-white p-3 shadow-sm lg:w-auto lg:min-w-[34rem]">
                            @csrf
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-error/10 text-error">
                                        <i class="ph ph-warning-circle text-lg"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-neutral-900">{{ __('Queue maintenance') }}</p>
                                        <p class="mt-0.5 text-xs text-neutral-500">{{ __('Remove pending jobs from a selected queue.') }}</p>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <label for="pending-clear-queue" class="sr-only">{{ __('Queue') }}</label>
                                    <select id="pending-clear-queue" name="queue" class="select-field h-11 min-w-40 bg-white py-0 text-sm shadow-none sm:w-44">
                                        @foreach ($queueNames as $queueName)
                                            <option value="{{ $queueName }}">{{ $queueName }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-white px-4 text-sm font-bold text-error shadow-sm transition-colors hover:bg-error hover:text-white focus:outline-none focus:ring-4 focus:ring-error/10">
                                        <i class="ph ph-trash text-base"></i>
                                        {{ __('Clear Queue') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    @endcan
                </div>
            </section>

            <section id="failed" class="{{ $activeTab === 'failed' ? '' : 'hidden' }}">
                <div class="flex flex-col gap-3 border-b border-neutral-100 p-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-neutral-950">{{ __('Failed Jobs') }}</h2>
                        <p class="mt-1 text-sm text-neutral-500">{{ __('Inspect failed jobs and trigger Laravel queue recovery commands.') }}</p>
                    </div>
                    @can('scheduler-queues.manage')
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('admin.scheduler-queues.failed.retry-all') }}">
                                @csrf
                                <x-ui.button type="submit" size="sm" variant="outline">{{ __('Retry All') }}</x-ui.button>
                            </form>
                            <form method="POST" action="{{ route('admin.scheduler-queues.failed.flush') }}">
                                @csrf
                                <x-ui.button type="submit" size="sm" variant="danger">{{ __('Flush Failed') }}</x-ui.button>
                            </form>
                        </div>
                    @endcan
                </div>

                <x-tables.table>
                    <thead>
                        <tr>
                            <th>{{ __('Job') }}</th>
                            <th>{{ __('Queue') }}</th>
                            <th>{{ __('Failed') }}</th>
                            <th>{{ __('Exception') }}</th>
                            <th class="text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($failedJobs as $job)
                            <tr>
                                <td data-th="{{ __('Job') }}">
                                    <div class="text-right lg:text-left">
                                        <p class="text-sm font-bold text-neutral-950">{{ $job['display_name'] }}</p>
                                        <p class="max-w-xl break-all text-xs text-neutral-500">{{ $job['job_class'] }}</p>
                                    </div>
                                </td>
                                <td data-th="{{ __('Queue') }}"><x-ui.badge variant="neutral">{{ $job['queue'] }}</x-ui.badge></td>
                                <td data-th="{{ __('Failed') }}" class="text-sm text-neutral-500">{{ $job['failed_at'] }}</td>
                                <td data-th="{{ __('Exception') }}" class="max-w-md text-sm text-neutral-500">{{ $job['exception_preview'] }}</td>
                                <td data-th="{{ __('Actions') }}" class="text-right">
                                    @can('scheduler-queues.manage')
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <form method="POST" action="{{ route('admin.scheduler-queues.failed.retry', $job['id']) }}">
                                                @csrf
                                                <x-ui.button type="submit" size="sm" variant="outline">{{ __('Retry') }}</x-ui.button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.scheduler-queues.failed.forget', $job['id']) }}">
                                                @csrf
                                                <x-ui.button type="submit" size="sm" variant="danger">{{ __('Forget') }}</x-ui.button>
                                            </form>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="table-empty-state">{{ __('No failed jobs found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-tables.table>

                <div class="border-t border-neutral-100 p-4">
                    <x-tables.pagination :paginator="$failedJobs" />
                </div>
            </section>

        </div>
    </div>
</x-layouts.admin>
