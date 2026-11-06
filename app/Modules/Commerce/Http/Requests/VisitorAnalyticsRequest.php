<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class VisitorAnalyticsRequest extends FormRequest
{
    /** @var array<string, int> Preset ranges in days, counting today. */
    public const RANGES = ['today' => 1, '7d' => 7, '30d' => 30, '90d' => 90];

    public function authorize(): bool
    {
        return $this->user()?->can('commerce.customers.view') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'range' => ['nullable', 'in:'.implode(',', [...array_keys(self::RANGES), 'custom'])],
            'from' => ['nullable', 'date', 'before_or_equal:today'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'before_or_equal:today'],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
            'country' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:120'],
            'device' => ['nullable', 'in:mobile,desktop,tablet,bot'],
            'os' => ['nullable', 'in:ios,android,windows,macos,linux,chromeos,other'],
            'customers' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to.after_or_equal' => __('The end date must be on or after the start date.'),
            'from.before_or_equal' => __('The start date cannot be in the future.'),
            'to.before_or_equal' => __('The end date cannot be in the future.'),
        ];
    }

    public function rangeKey(): string
    {
        if ($this->filled('date')) {
            return 'custom';
        }

        return (string) ($this->validated('range') ?? ($this->filled('from') ? 'custom' : '7d'));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function period(): array
    {
        if ($this->filled('date')) {
            $day = Carbon::parse((string) $this->validated('date'));

            return [$day->copy()->startOfDay(), $day->copy()->endOfDay()];
        }
        if ($this->rangeKey() === 'custom' && $this->filled('from')) {
            $from = Carbon::parse((string) $this->validated('from'))->startOfDay();
            $to = Carbon::parse((string) ($this->validated('to') ?? today()->toDateString()))->endOfDay();

            return [$from->max($to->copy()->subDays(365)->startOfDay()), $to];
        }
        $days = self::RANGES[$this->rangeKey()] ?? 7;

        return [today()->subDays($days - 1)->startOfDay(), today()->endOfDay()];
    }
}
