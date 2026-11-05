<x-layouts.user :title="__('AI Auto-Reply')">
    <div class="flex items-center gap-3">
        <a href="{{ route('user.chatbots.index') }}" class="row-action" aria-label="{{ __('Back to chatbots') }}">
            <i class="ph ph-arrow-left text-lg"></i>
        </a>
        <div>
            <h2 class="heading-2">{{ __('AI Auto-Reply') }}</h2>
            <p class="m-text mt-1">{{ __('Let a chatbot answer customers on WhatsApp, Messenger and Instagram. It uses its knowledge base and your live products, follows your Retail/Wholesale settings and sends product color photos.') }}</p>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-md border border-success/20 bg-success/10 px-4 py-3 text-sm font-semibold text-success">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('user.chatbots.channel-ai.update') }}" class="mt-6 max-w-2xl space-y-4">
        @csrf
        @method('PUT')

        @foreach ($channels as $provider => $label)
            <div class="app-card space-y-4 p-5 sm:p-6">
                <input type="hidden" name="channels[{{ $provider }}][enabled]" value="0">
                <label class="flex items-center gap-2.5">
                    <input type="checkbox" name="channels[{{ $provider }}][enabled]" value="1" class="app-checkbox" @checked(old("channels.{$provider}.enabled", $settings[$provider]['enabled']))>
                    <span class="text-sm font-semibold text-title">{{ __('Reply to :channel messages with AI', ['channel' => $label]) }}</span>
                </label>

                <div>
                    <label for="chatbot_{{ $provider }}" class="form-label">{{ __('Chatbot') }}</label>
                    <select id="chatbot_{{ $provider }}" name="channels[{{ $provider }}][chatbot_id]" class="form-input">
                        <option value="">{{ __('Select a chatbot') }}</option>
                        @foreach ($chatbots as $chatbot)
                            <option value="{{ $chatbot->id }}" @selected((int) old("channels.{$provider}.chatbot_id", $settings[$provider]['chatbot_id']) === $chatbot->id)>{{ $chatbot->name }}</option>
                        @endforeach
                    </select>
                    @error("channels.{$provider}.chatbot_id")<p class="form-hint text-error">{{ $message }}</p>@enderror
                </div>
            </div>
        @endforeach

        <p class="form-hint">{{ __('Conversations assigned to a team member, or questions the bot cannot answer, are left for a human. WhatsApp replies are only sent inside the 24-hour customer window.') }}</p>

        <button type="submit" class="btn btn-primary">{{ __('Save settings') }}</button>
    </form>
</x-layouts.user>
