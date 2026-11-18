<?php

namespace App\Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Model;

class CarrierAccount extends Model
{
    public const CARRIERS = ['ups' => 'UPS', 'fedex' => 'FedEx', 'aramex' => 'Aramex'];

    public const ENVIRONMENTS = ['sandbox', 'production'];

    protected $table = 'shipping_carrier_accounts';

    protected $fillable = [
        'workspace_id',
        'name',
        'carrier',
        'account_number',
        'environment',
        'origin_name',
        'origin_address_line1',
        'origin_city',
        'origin_state',
        'origin_postal_code',
        'origin_country_code',
        'origin_phone',
        'exchange_rate',
        'options',
        'last_success_at',
        'last_error',
        'last_error_at',
        'credentials',
        'dim_divisor',
        'weight_rounding_step',
        'is_active',
        'booking_enabled',
        'notes',
    ];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'options' => 'array',
            'exchange_rate' => 'decimal:8',
            'last_success_at' => 'datetime',
            'last_error_at' => 'datetime',
            'dim_divisor' => 'integer',
            'weight_rounding_step' => 'decimal:3',
            'is_active' => 'boolean',
            'booking_enabled' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function carrierLabel(): string
    {
        return self::CARRIERS[$this->carrier] ?? strtoupper((string) $this->carrier);
    }

    public function hasCredentials(): bool
    {
        return array_filter((array) $this->credentials) !== [];
    }

    public function credential(string $key): ?string
    {
        $value = data_get($this->credentials, $key);

        return filled($value) ? (string) $value : null;
    }

    public function option(string $key, ?string $default = null): ?string
    {
        $value = data_get($this->options, $key);

        return filled($value) ? (string) $value : $default;
    }

    public function hasOrigin(): bool
    {
        return filled($this->origin_country_code) && filled($this->origin_city) && filled($this->origin_postal_code);
    }

    /**
     * Credential keys the courier needs before its API can be called.
     *
     * @return list<string>
     */
    public function requiredCredentialKeys(): array
    {
        return $this->carrier === 'aramex'
            ? ['username', 'password', 'account_pin', 'account_entity', 'account_country_code']
            : ['client_id', 'client_secret'];
    }

    /**
     * What is still missing before live rates can be requested, in plain words.
     *
     * @return list<string>
     */
    public function liveRateGaps(): array
    {
        $gaps = [];
        if (blank($this->account_number)) {
            $gaps[] = 'account number';
        }
        foreach ($this->requiredCredentialKeys() as $key) {
            if ($this->credential($key) === null) {
                $gaps[] = str_replace('_', ' ', $key);
            }
        }
        if (! $this->hasOrigin()) {
            $gaps[] = 'ship-from address';
        }

        return $gaps;
    }

    public function isReadyForLiveRates(): bool
    {
        return $this->is_active && $this->liveRateGaps() === [];
    }

    /**
     * Tracking any number only needs working API credentials, not a ship-from address.
     */
    public function canTrack(): bool
    {
        foreach ($this->requiredCredentialKeys() as $key) {
            if ($this->credential($key) === null) {
                return false;
            }
        }

        return $this->is_active && ($this->carrier !== 'aramex' || filled($this->account_number));
    }

    /**
     * What is still missing before shipments can be booked through the courier API.
     *
     * @return list<string>
     */
    public function bookingGaps(): array
    {
        $gaps = $this->liveRateGaps();
        if (blank($this->origin_phone)) {
            $gaps[] = 'ship-from phone';
        }
        if (blank($this->origin_address_line1)) {
            $gaps[] = 'ship-from street address';
        }
        if (! $this->booking_enabled) {
            $gaps[] = 'API booking switched on';
        }

        return array_values(array_unique($gaps));
    }
}
