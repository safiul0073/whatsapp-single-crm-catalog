<?php

namespace App\Modules\MetaAds\Http\Requests;

use App\Modules\MetaAds\Models\MetaAd;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMetaAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('meta-ads.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'ad_account_id' => ['required', 'string', 'max:64'],
            'page_id' => ['required', 'string', 'max:64'],
            'instagram_account_id' => ['nullable', 'string', 'max:64'],
            'kind' => ['required', Rule::in(MetaAd::KINDS)],
            'destination' => ['required_if:kind,'.MetaAd::KIND_PRODUCT, 'nullable', Rule::in(MetaAd::DESTINATIONS)],
            'product_id' => ['required_if:kind,'.MetaAd::KIND_PRODUCT, 'nullable', 'integer'],
            'post_id' => ['required_if:kind,'.MetaAd::KIND_BOOST_POST, 'nullable', 'string', 'max:100'],
            'content_id' => ['required_if:kind,'.MetaAd::KIND_PARTNERSHIP, 'nullable', 'string', 'max:100'],
            'ad_code' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', Rule::in(['INSTAGRAM', 'FACEBOOK'])],
            'message' => ['nullable', 'string', 'max:2000'],
            'daily_budget' => ['required', 'numeric', 'min:1', 'max:100000'],
            'countries' => ['required', 'array', 'min:1', 'max:25'],
            'countries.*' => ['required', 'string', 'size:2', 'alpha'],
            'age_min' => ['required', 'integer', 'min:18', 'max:65'],
            'age_max' => ['required', 'integer', 'gte:age_min', 'max:65'],
            'gender' => ['required', Rule::in(['all', 'men', 'women'])],
            'starts_at' => ['nullable', 'date', 'after_or_equal:today'],
            'ends_at' => ['nullable', 'date', 'after:starts_at', 'after:now'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('kind') === MetaAd::KIND_PARTNERSHIP && $this->input('platform') === 'FACEBOOK' && blank($this->input('ad_code'))) {
                    $validator->errors()->add('ad_code', __('Facebook creator posts need the creator\'s partnership ad code.'));
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'product_id.required_if' => __('Choose the product to promote.'),
            'post_id.required_if' => __('Choose the Page post to boost.'),
            'content_id.required_if' => __('Choose the creator post to promote.'),
            'countries.required' => __('Choose at least one country to show the ad in.'),
            'daily_budget.min' => __('The daily budget must be at least 1.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $countries = $this->input('countries');

        if (is_string($countries)) {
            $this->merge(['countries' => array_values(array_filter(array_map(fn (string $code): string => strtoupper(trim($code)), explode(',', $countries))))]);
        }
    }
}
