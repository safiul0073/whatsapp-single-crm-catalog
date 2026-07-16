<x-layouts.admin :title="__('Edit Template: :name', ['name' => $notificationTemplate->name])">
    <form method="POST" action="{{ route('admin.notification-templates.update', $notificationTemplate) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="flex items-center justify-between">
            <div>
                <h1 class="heading-4 text-neutral-950">{{ $notificationTemplate->name }}</h1>
                <p class="text-sm text-neutral-400 mt-1">
                    <code class="rounded bg-neutral-100 px-1.5 py-0.5 text-xs dark:bg-neutral-800">{{ $notificationTemplate->slug }}</code>
                    @if($notificationTemplate->description)
                        <span class="ml-2">{{ $notificationTemplate->description }}</span>
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-3">
                <x-ui.button variant="outline" href="{{ route('admin.notification-templates.index') }}">
                    <i class="ph ph-arrow-left"></i> {{ __('Back') }}
                </x-ui.button>
                <x-forms.submit :label="__('Save Changes')" />
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2" x-data="{ activeTab: 'email' }">
                <div class="flex border-b border-neutral-200 dark:border-neutral-700 mb-6 overflow-x-auto">
                    <button type="button" @click="activeTab = 'email'"
                            :class="activeTab === 'email' ? 'border-primary text-primary' : 'border-transparent text-neutral-400 hover:text-neutral-600'"
                            class="flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
                        <i class="ph ph-envelope"></i> {{ __('Email') }}
                    </button>
                    <button type="button" @click="activeTab = 'sms'"
                            :class="activeTab === 'sms' ? 'border-primary text-primary' : 'border-transparent text-neutral-400 hover:text-neutral-600'"
                            class="flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
                        <i class="ph ph-chat-text"></i> {{ __('SMS') }}
                    </button>
                    <button type="button" @click="activeTab = 'in_app'"
                            :class="activeTab === 'in_app' ? 'border-primary text-primary' : 'border-transparent text-neutral-400 hover:text-neutral-600'"
                            class="flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
                        <i class="ph ph-bell"></i> {{ __('In-App') }}
                    </button>
                    <button type="button" @click="activeTab = 'push'"
                            :class="activeTab === 'push' ? 'border-primary text-primary' : 'border-transparent text-neutral-400 hover:text-neutral-600'"
                            class="flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
                        <i class="ph ph-broadcast"></i> {{ __('Push') }}
                    </button>
                    <button type="button" @click="activeTab = 'settings'"
                            :class="activeTab === 'settings' ? 'border-primary text-primary' : 'border-transparent text-neutral-400 hover:text-neutral-600'"
                            class="flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap">
                        <i class="ph ph-gear"></i> {{ __('Settings') }}
                    </button>
                </div>

                <div x-show="activeTab === 'email'" x-cloak class="section-card space-y-4">
                    <h3 class="text-base font-semibold text-neutral-900">{{ __('Email Template') }}</h3>
                    <x-forms.input :label="__('Subject')" name="email_subject" :value="old('email_subject', $notificationTemplate->email_subject)" :placeholder="__('Email subject with {{variables}}')" />
                    <x-forms.editor :label="__('Body')" name="email_body" :value="old('email_body', $notificationTemplate->email_body)" />
                </div>

                <div x-show="activeTab === 'sms'" x-cloak class="section-card space-y-4"
                     x-data="{ smsBody: {{ Js::from(old('sms_body', $notificationTemplate->sms_body ?? '')) }} }">
                    <h3 class="text-base font-semibold text-neutral-900">{{ __('SMS Template') }}</h3>
                    <div>
                        <x-forms.textarea :label="__('Message')" name="sms_body" x-model="smsBody" :value="old('sms_body', $notificationTemplate->sms_body)" rows="4" :placeholder="__('SMS message with {{variables}}')" />
                        <p class="text-xs text-neutral-400 mt-1">
                            <span x-text="1600 - (smsBody?.length || 0)"></span> {{ __('characters remaining') }} ({{ __('max 1600') }})
                        </p>
                    </div>
                </div>

                <div x-show="activeTab === 'in_app'" x-cloak class="section-card space-y-4">
                    <h3 class="text-base font-semibold text-neutral-900">{{ __('In-App Notification') }}</h3>
                    <x-forms.input :label="__('Title')" name="in_app_title" :value="old('in_app_title', $notificationTemplate->in_app_title)" :placeholder="__('Notification title')" />
                    <x-forms.textarea :label="__('Body')" name="in_app_body" :value="old('in_app_body', $notificationTemplate->in_app_body)" rows="3" :placeholder="__('Notification body text')" />
                </div>

                <div x-show="activeTab === 'push'" x-cloak class="section-card space-y-4">
                    <h3 class="text-base font-semibold text-neutral-900">{{ __('Push Notification') }}</h3>
                    <p class="text-sm text-neutral-400">{{ __('Used for both Web Push (VAPID) and Mobile Push (Firebase).') }}</p>
                    <x-forms.input :label="__('Title')" name="push_title" :value="old('push_title', $notificationTemplate->push_title)" :placeholder="__('Push notification title')" />
                    <x-forms.textarea :label="__('Body')" name="push_body" :value="old('push_body', $notificationTemplate->push_body)" rows="3" :placeholder="__('Push notification body')" />
                </div>

                <div x-show="activeTab === 'settings'" x-cloak class="section-card space-y-6">
                    <h3 class="text-base font-semibold text-neutral-900">{{ __('Template Settings') }}</h3>

                    <x-forms.input :label="__('Name')" name="name" :value="old('name', $notificationTemplate->name)" required />

                    <x-forms.textarea :label="__('Description')" name="description" :value="old('description', $notificationTemplate->description)" rows="2" :placeholder="__('Brief description of when this notification is triggered')" />

                    <div>
                        <label class="form-label mb-3">{{ __('Enabled Channels') }}</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @php
                                $availableChannels = [
                                    'email' => ['label' => 'Email', 'icon' => 'ph-envelope'],
                                    'sms' => ['label' => 'SMS', 'icon' => 'ph-chat-text'],
                                    'in_app' => ['label' => 'In-App', 'icon' => 'ph-bell'],
                                    'web_push' => ['label' => 'Web Push', 'icon' => 'ph-broadcast'],
                                    'mobile_push' => ['label' => 'Mobile Push', 'icon' => 'ph-device-mobile'],
                                ];
                                $templateChannels = old('channels', $notificationTemplate->channels ?? []);
                            @endphp
                            @foreach($availableChannels as $channelKey => $channelInfo)
                                <label class="flex items-center gap-3 rounded-lg border border-neutral-200 dark:border-neutral-700 p-3 cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors">
                                    <input type="checkbox" name="channels[]" value="{{ $channelKey }}"
                                           class="custom-checkbox"
                                           @checked(in_array($channelKey, $templateChannels))>
                                    <i class="ph {{ $channelInfo['icon'] }} text-lg text-neutral-500"></i>
                                    <span class="text-sm text-neutral-700 dark:text-neutral-300">{{ __($channelInfo['label']) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <x-forms.toggle :label="__('Active')" name="is_active" :checked="old('is_active', $notificationTemplate->is_active)" />
                </div>
            </div>

            <div class="xl:col-span-1">
                <div class="section-card sticky top-24 space-y-4">
                    <h4 class="text-sm font-semibold text-neutral-900">{{ __('Available Variables') }}</h4>
                    <p class="text-xs text-neutral-400">{{ __('Click to copy. Use in any template field.') }}</p>

                    @if(!empty($notificationTemplate->variables))
                        <div class="space-y-2">
                            @foreach($notificationTemplate->variables as $varName => $varDesc)
                                @php $varTag = '{{' . $varName . '}}'; @endphp
                                <div>
                                    <button type="button"
                                            onclick="navigator.clipboard.writeText('{{ $varTag }}'); this.querySelector('.copied').classList.remove('hidden'); setTimeout(() => this.querySelector('.copied').classList.add('hidden'), 1500)"
                                            class="inline-flex items-center gap-1.5 rounded-md bg-primary/10 px-2.5 py-1 text-xs font-mono font-medium text-primary hover:bg-primary/20 transition-colors">
                                        {{ $varTag }}
                                        <span class="copied hidden text-success text-[10px]"><i class="ph ph-check"></i></span>
                                    </button>
                                    <p class="text-[11px] text-neutral-400 mt-0.5 pl-1">{{ $varDesc }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-neutral-400 italic">{{ __('No template-specific variables defined.') }}</p>
                    @endif

                    <hr class="border-neutral-200 dark:border-neutral-700">

                    <h5 class="text-xs font-semibold text-neutral-500 uppercase tracking-wider">{{ __('Global Variables') }}</h5>
                    <div class="space-y-2">
                        @foreach(['site_name' => 'Application name', 'site_url' => 'Application URL', 'current_year' => 'Current year'] as $gVar => $gDesc)
                            @php $gVarTag = '{{' . $gVar . '}}'; @endphp
                            <div>
                                <button type="button"
                                        onclick="navigator.clipboard.writeText('{{ $gVarTag }}'); this.querySelector('.copied').classList.remove('hidden'); setTimeout(() => this.querySelector('.copied').classList.add('hidden'), 1500)"
                                        class="inline-flex items-center gap-1.5 rounded-md bg-neutral-100 dark:bg-neutral-800 px-2.5 py-1 text-xs font-mono font-medium text-neutral-600 dark:text-neutral-400 hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors">
                                    {{ $gVarTag }}
                                    <span class="copied hidden text-success text-[10px]"><i class="ph ph-check"></i></span>
                                </button>
                                <p class="text-[11px] text-neutral-400 mt-0.5 pl-1">{{ __($gDesc) }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </form>
</x-layouts.admin>
