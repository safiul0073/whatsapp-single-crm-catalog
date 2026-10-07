<?php

namespace App\Modules\SocialWidgets\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicSocialWidgetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource['widget']->public_token,
            'layout' => $this->resource['widget']->layout->value,
            'settings' => $this->resource['widget']->mergedSettings(),
            'profile' => $this->resource['feed']['profile'],
            'posts' => $this->resource['feed']['posts'],
        ];
    }
}
