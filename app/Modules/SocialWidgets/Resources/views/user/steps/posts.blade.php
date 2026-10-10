<div class="space-y-3">
    <x-social-widgets::accordion section="post-elements" icon="ph-cursor-click" :title="__('Post Elements')">
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-2.5">
                <x-social-widgets::checkbox model="settings.post.caption" :label="__('Caption')" />
                <x-social-widgets::checkbox model="settings.post.likes" :label="__('Likes')" />
                <x-social-widgets::checkbox model="settings.post.comments" :label="__('Comments')" />
            </div>
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-title">{{ __('Number of posts') }}</span>
                    <span class="rounded-md border border-neutral-200 bg-white px-2 py-0.5 font-mono text-[11px] text-title" x-text="settings.post.limit"></span>
                </div>
                <input type="range" min="1" max="48" class="mt-2 w-full accent-title" x-model.number="settings.post.limit">
            </div>
        </div>
    </x-social-widgets::accordion>

    <x-social-widgets::accordion section="actions" icon="ph-hand-pointing" :title="__('Actions & Popup')">
        <div class="space-y-4">
            <div>
                <p class="font-mono text-[10px] font-bold uppercase tracking-[0.15em] text-body">{{ __('Click action') }}</p>
                <div class="mt-1.5">
                    <x-social-widgets::segmented model="settings.click.action" :options="['popup' => __('Open Popup'), 'link' => __('Open Instagram')]" />
                </div>
            </div>
            <div class="space-y-4 rounded-md border border-neutral-200 bg-white p-3" x-show="settings.click.action === 'popup'">
                <p class="font-mono text-[10px] font-bold uppercase tracking-[0.15em] text-body">{{ __('Popup content') }}</p>
                <div class="space-y-2.5">
                    <x-social-widgets::checkbox model="settings.click.popup.header" :label="__('Header')" />
                    <x-social-widgets::checkbox model="settings.click.popup.caption" :label="__('Caption')" />
                    <x-social-widgets::checkbox model="settings.click.popup.comments" :label="__('Show Comments')" />
                    <x-social-widgets::checkbox model="settings.click.popup.followButton" :label="__('Show Follow Button')" />
                    <x-social-widgets::checkbox model="settings.click.popup.counts" :label="__('Show Counts')" />
                    <x-social-widgets::checkbox model="settings.click.popup.shareButton" :label="__('Show Share Button')" />
                </div>
                <div class="border-t border-neutral-100 pt-3">
                    <x-social-widgets::toggle model="settings.click.swipe" :label="__('Swipe Between Posts in Popup')" :hint="__('Visitors can use arrow keys or buttons to move to the next or previous post without closing it.')" />
                </div>
            </div>
        </div>
    </x-social-widgets::accordion>
</div>
