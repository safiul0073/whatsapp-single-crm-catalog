<?php

namespace App\Modules\MetaSocial\Models;

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A workspace's single "Connect your Meta Business" login: the business token, every asset Meta
 * shared (WhatsApp numbers, Pages, Instagram, ad accounts, catalogs) and the ones the user turned on.
 */
class MetaBusinessConnection extends Model
{
    protected $fillable = [
        'workspace_id',
        'business_id',
        'business_name',
        'status',
        'credentials',
        'scopes',
        'assets',
        'selected',
        'last_error',
        'connected_at',
        'last_synced_at',
    ];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'scopes' => 'array',
            'assets' => 'array',
            'selected' => 'array',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function accessToken(): ?string
    {
        $token = data_get($this->credentials ?? [], 'access_token');

        return filled($token) ? (string) $token : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function assetsOfType(string $type): array
    {
        return array_values($this->assets[$type] ?? []);
    }

    /**
     * @return array<int, string>
     */
    public function selectedOfType(string $type): array
    {
        return array_values(array_map('strval', $this->selected[$type] ?? []));
    }
}
