<?php

namespace App\Modules\SchedulerQueue\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\SchedulerQueue\Models\SchedulerEntry;
use App\Modules\SchedulerQueue\Services\ManagedSchedulerService;
use App\Modules\SchedulerQueue\Services\QueueMonitorService;
use App\Modules\SchedulerQueue\Services\SchedulerRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class SchedulerQueueController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:scheduler-queues.view', only: ['index']),
            new Middleware('permission:scheduler-queues.edit', only: ['update']),
            new Middleware('permission:scheduler-queues.run', only: ['run']),
            new Middleware('permission:scheduler-queues.manage', only: ['retry', 'retryAll', 'forget', 'flush', 'clear', 'restart']),
        ];
    }

    public function __construct(
        protected ManagedSchedulerService $scheduler,
        protected QueueMonitorService $queues,
        protected SchedulerRegistry $registry
    ) {}

    public function index(Request $request): View
    {
        $this->scheduler->syncRegisteredEntries();

        return view('scheduler-queue::admin.index', [
            'entries' => SchedulerEntry::query()->orderBy('label')->get(),
            'frequencies' => $this->registry->frequencies(),
            'queueNames' => $this->queues->queueNames(),
            'summary' => $this->queues->summary(),
            'pendingJobs' => $this->queues->pendingJobs($request->only(['queue', 'status'])),
            'failedJobs' => $this->queues->failedJobs($request->only(['queue'])),
            'activeTab' => in_array($request->query('tab'), ['scheduler', 'pending', 'failed'], true)
                ? $request->query('tab')
                : 'scheduler',
            'filters' => $request->only(['queue', 'status']),
        ]);
    }

    public function update(Request $request, SchedulerEntry $schedulerEntry): RedirectResponse
    {
        $validated = $request->validate([
            'frequency' => ['required', 'string', 'in:'.implode(',', array_keys($this->registry->frequencies()))],
            'queue' => ['required', 'string', 'max:100'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        $schedulerEntry->update([
            'frequency' => $validated['frequency'],
            'queue' => $validated['queue'],
            'enabled' => $request->boolean('enabled'),
        ]);

        return redirect()
            ->to(route('admin.scheduler-queues.index').'#scheduler')
            ->with('success', __('Scheduler entry updated successfully.'));
    }

    public function run(SchedulerEntry $schedulerEntry): RedirectResponse
    {
        $this->scheduler->run($schedulerEntry);

        return redirect()
            ->to(route('admin.scheduler-queues.index').'#scheduler')
            ->with('success', __('Scheduler entry dispatched successfully.'));
    }

    public function retry(string $id): RedirectResponse
    {
        $this->queues->retry($id);

        return $this->backTo('failed', __('Failed job retry requested.'));
    }

    public function retryAll(): RedirectResponse
    {
        $this->queues->retryAll();

        return $this->backTo('failed', __('All failed jobs retry requested.'));
    }

    public function forget(string $id): RedirectResponse
    {
        $this->queues->forget($id);

        return $this->backTo('failed', __('Failed job forgotten.'));
    }

    public function flush(): RedirectResponse
    {
        $this->queues->flush();

        return $this->backTo('failed', __('Failed jobs flushed.'));
    }

    public function clear(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'queue' => ['required', 'string', 'max:100'],
        ]);

        abort_unless(in_array($validated['queue'], $this->queues->queueNames(), true), 422, __('Unknown queue selected.'));

        $this->queues->clear($validated['queue']);

        return $this->backTo('pending', __('Pending queue cleared.'));
    }

    public function restart(): RedirectResponse
    {
        $this->queues->restart();

        return $this->backTo('scheduler', __('Queue workers restart signal sent.'));
    }

    protected function backTo(string $tab, string $message): RedirectResponse
    {
        return redirect()
            ->to(route('admin.scheduler-queues.index', ['tab' => $tab]).'#'.$tab)
            ->with('success', $message);
    }
}
