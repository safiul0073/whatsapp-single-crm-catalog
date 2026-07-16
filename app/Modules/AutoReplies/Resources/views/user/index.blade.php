<x-layouts.user :title="__('Auto Replies')">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="heading-2">Auto Replies</h2>
            <p class="m-text mt-1">Automatically respond to incoming messages by keyword, first message, fallback, or business-hour trigger.</p>
        </div>
        <a href="{{ route('user.auto-replies.create') }}" class="btn-sm btn-primary">
            <i class="ph ph-plus text-base"></i>
            Add Rule
        </a>
    </div>

    @if (session('status'))
        <div class="app-card mt-5 border-success/30 bg-success/5 p-4 text-sm font-medium text-success">
            {{ session('status') }}
        </div>
    @endif

    @if ($rules->isNotEmpty())
        <div class="mt-6 space-y-3">
            @foreach ($rules as $rule)
                @php
                    $payload = $rule->reply_payload ?? [];
                    $preview = match ($rule->reply_type) {
                        'template' => 'Template: '.($payload['template_name'] ?? 'Not selected'),
                        'media' => ($payload['filename'] ?? $payload['media_url'] ?? $payload['url'] ?? 'Media reply'),
                        default => $rule->reply_text,
                    };
                @endphp
                <article class="rule-row">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge {{ $rule->trigger_type === 'keyword' ? 'badge-info' : 'badge-deep' }}">{{ $rule->trigger_label }}</span>
                            @if ($rule->trigger_value)
                                <span class="font-semibold text-title">{{ $rule->trigger_value }}</span>
                                <span class="text-xs text-neutral-400">({{ $rule->match_type }})</span>
                            @endif
                            <span class="rule-pill"><i class="ph ph-arrow-right text-xs"></i>{{ $rule->reply_type_label }}</span>
                            <span class="text-xs text-neutral-400">priority {{ $rule->priority }}</span>
                            @unless ($rule->is_active)
                                <span class="badge badge-warning">Disabled</span>
                            @endunless
                        </div>
                        <h3 class="mt-2 text-sm font-semibold text-title">{{ $rule->name }}</h3>
                        <p class="rule-row__msg">{{ $preview }}</p>
                    </div>
                    <div class="rule-row__actions">
                        <a href="{{ route('user.auto-replies.edit', $rule) }}" class="row-action" aria-label="Edit rule">
                            <i class="ph ph-pencil-simple text-base"></i>
                        </a>

                        <form method="POST" action="{{ route('user.auto-replies.toggle', $rule) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="row-action" aria-label="{{ $rule->is_active ? 'Disable rule' : 'Enable rule' }}">
                                <i class="ph {{ $rule->is_active ? 'ph-toggle-right text-success' : 'ph-toggle-left text-neutral-400' }} text-xl"></i>
                            </button>
                        </form>

                        <form method="POST" action="{{ route('user.auto-replies.destroy', $rule) }}">
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="row-action hover:text-error"
                                aria-label="Delete rule"
                                data-confirm
                                data-confirm-title="Delete auto-reply rule?"
                                data-confirm-body="This rule will stop responding to incoming messages immediately. This can't be undone."
                                data-confirm-label="Delete"
                                data-confirm-variant="error"
                            >
                                <i class="ph ph-trash text-base"></i>
                            </button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $rules->links() }}
        </div>
    @else
        <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
            <span class="grid h-12 w-12 place-items-center rounded-xl bg-primary/10 text-primary">
                <i class="ph ph-chat-dots text-2xl"></i>
            </span>
            <h3 class="heading-4 mt-4">No auto-reply rules yet</h3>
            <p class="m-text mt-1 max-w-sm">Add a rule to automatically respond to incoming messages by keyword or trigger.</p>
            <a href="{{ route('user.auto-replies.create') }}" class="btn-sm btn-primary mt-5">
                <i class="ph ph-plus text-base"></i>
                Add Rule
            </a>
        </div>
    @endif
</x-layouts.user>
