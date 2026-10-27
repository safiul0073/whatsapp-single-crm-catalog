<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LiveAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'liveSettings', 'updateSettings' => [
                'enabled' => 'nullable|boolean',
                'default_platforms' => 'nullable|array',
                'default_platforms.*' => 'string|in:storefront,facebook',
                'show_header_live_badge' => 'nullable|boolean',
                'auto_save_recording' => 'nullable|boolean',
                'auto_publish_to_slider' => 'nullable|boolean',
                'default_slider_id' => 'nullable|integer',
                'allow_guest_chat' => 'nullable|boolean',
                'stun_url' => 'nullable|string|max:500',
                'turn_url' => 'nullable|string|max:500',
                'turn_username' => 'nullable|string|max:200',
                'turn_credential' => 'nullable|string|max:500',
                'facebook' => 'nullable|array',
                'facebook.enabled' => 'nullable|boolean',
                'facebook.use_connected_messenger_page' => 'nullable|boolean',
                'facebook.page_id' => 'nullable|string|max:120',
                'facebook.page_name' => 'nullable|string|max:200',
                'facebook.page_access_token' => 'nullable|string|max:2000',
                'facebook.sync_facebook_comments' => 'nullable|boolean',
                'facebook.auto_upload_vod_fallback' => 'nullable|boolean',
            ],
            'store', 'storeLive' => [
                'title' => 'required|string|max:200',
                'description' => 'nullable|string|max:5000',
                'broadcast_platforms' => 'nullable|array',
                'broadcast_platforms.*' => 'string|in:storefront,facebook',
                'product_ids' => 'nullable|array|max:50',
                'product_ids.*' => 'integer',
                'pinned_product_id' => 'nullable|integer',
                'chat_enabled' => 'nullable|boolean',
                'add_to_slider' => 'nullable|boolean',
                'slider_id' => 'nullable|integer',
                'scheduled_at' => 'nullable|date',
                'duration_seconds' => 'nullable|integer|min:0',
                'thumbnail' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:10240',
                'video' => 'nullable|file|mimes:mp4,webm,mov|max:512000',
            ],
            'start' => [
                'broadcast_platforms' => 'nullable|array',
                'broadcast_platforms.*' => 'string|in:storefront,facebook',
            ],
            'signal' => [
                'type' => 'required|string|in:offer,answer,ice-candidate,broadcaster-ready',
                'sender_peer_id' => 'required|string|max:120',
                'target_peer_id' => 'nullable|string|max:120',
                'payload' => 'nullable|array',
            ],
            'pinProduct' => [
                'product_id' => 'nullable|integer',
            ],
            'comment' => [
                'body' => 'required|string|min:1|max:500',
                'is_pinned' => 'nullable|boolean',
                'sync_facebook' => 'nullable|boolean',
            ],
            'chunk' => [
                'chunk' => 'required|file|max:102400',
            ],
            'stop', 'stopLive' => [
                'video' => 'nullable|file|mimes:mp4,webm,mov|max:512000',
                'thumbnail' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:10240',
                'add_to_slider' => 'nullable|boolean',
                'slider_id' => 'nullable|integer',
                'duration_seconds' => 'nullable|integer|min:0',
            ],
            'toggleSlider', 'toggleLiveSlider' => [
                'enable' => 'nullable|boolean',
                'slider_id' => 'nullable|integer',
            ],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Please enter a title for the live stream.',
            'broadcast_platforms.*.in' => 'Broadcast platform must be storefront or facebook.',
            'video.mimes' => 'Recorded video must be an MP4, WebM, or MOV file.',
        ];
    }
}
