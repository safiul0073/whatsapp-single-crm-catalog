<x-layouts.user :title="__('WhatsApp AI Auto-Reply')">
    <div class="flex items-center gap-3">
        <a href="{{ route('user.chatbots.index') }}" class="row-action" aria-label="{{ __('Back to chatbots') }}">
            <i class="ph ph-arrow-left text-lg"></i>
        </a>
        <div>
            <h2 class="heading-2">{{ __('WhatsApp AI Auto-Reply') }}</h2>
            <p class="m-text mt-1">{{ __('Let a chatbot answer incoming WhatsApp messages using its knowledge base.') }}</p>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-md border border-success/20 bg-success/10 px-4 py-3 text-sm font-semibold text-success">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('user.chatbots.whatsapp-ai.update') }}" class="app-card mt-6 max-w-2xl space-y-5 p-5 sm:p-6">
        @csrf
        @method('PUT')

        <label class="flex items-center gap-2.5">
            <input type="checkbox" name="enabled" value="1" class="app-checkbox" @checked(old('enabled', $settings['enabled']))>
            <span class="text-sm text-body">{{ __('Automatically reply to WhatsApp messages with AI') }}</span>
        </label>

        <div>
            <label for="chatbot_id" class="form-label">{{ __('Chatbot') }}</label>
            <select id="chatbot_id" name="chatbot_id" class="form-input">
                <option value="">{{ __('Select a chatbot') }}</option>
                @foreach ($chatbots as $chatbot)
                    <option value="{{ $chatbot->id }}" @selected((int) old('chatbot_id', $settings['chatbot_id']) === $chatbot->id)>{{ $chatbot->name }}</option>
                @endforeach
            </select>
            @error('chatbot_id')<p class="form-hint text-error">{{ $message }}</p>@enderror
            <p class="form-hint">{{ __('Replies use the chatbot knowledge bases. Conversations assigned to a team member, or questions the bot cannot answer, are left for a human.') }}</p>
        </div>

        <button type="submit" class="btn btn-primary">{{ __('Save settings') }}</button>
    </form>
</x-layouts.user>
