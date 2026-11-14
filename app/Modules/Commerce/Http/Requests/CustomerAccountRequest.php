<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Contacts\Models\Contact;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Rules\CountryCode;
use App\Rules\InternationalPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class CustomerAccountRequest extends FormRequest
{
    protected $errorBag = 'customerForm';

    public function authorize(): bool
    {
        return $this->user()?->can('commerce.customers.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $address = is_array($this->input('address')) ? $this->input('address') : [];

        $this->merge([
            'phone' => InternationalPhone::normalize($this->input('phone')) ?? $this->input('phone'),
            'email' => filled($this->input('email')) ? strtolower(trim((string) $this->input('email'))) : null,
            'address' => [...$address, 'country' => filled($address['country'] ?? null) ? strtoupper((string) $address['country']) : null],
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30', new InternationalPhone],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)],
            'address' => ['nullable', 'array'],
            'address.line1' => ['nullable', 'string', 'max:255'],
            'address.line2' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'required_with:address.line1', 'string', 'max:120'],
            'address.state' => ['nullable', 'string', 'max:120'],
            'address.postal_code' => ['nullable', 'string', 'max:30'],
            'address.country' => ['nullable', new CountryCode],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $workspaceId = app(WorkspaceResolver::class)->current($this->user())?->id;
                $current = $this->route('customer');
                $ignoreCustomerId = $current instanceof Customer ? $current->id : null;
                $ownContactId = $current instanceof Customer ? $current->contact_id : null;

                $phoneContact = Contact::query()->where('workspace_id', $workspaceId)->where('phone', $this->input('phone'))->first();
                if ($phoneContact && $phoneContact->id !== $ownContactId) {
                    $taken = Customer::query()
                        ->where('workspace_id', $workspaceId)
                        ->where('contact_id', $phoneContact->id)
                        ->when($ignoreCustomerId, fn ($query) => $query->whereKeyNot($ignoreCustomerId))
                        ->exists();
                    if ($taken || $current instanceof Customer) {
                        $validator->errors()->add('phone', __('Another customer or contact already uses this phone number.'));
                    }
                }

                $taken = Customer::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('verified_phone', $this->input('phone'))
                    ->when($ignoreCustomerId, fn ($query) => $query->whereKeyNot($ignoreCustomerId))
                    ->exists();
                if ($taken) {
                    $validator->errors()->add('phone', __('Another customer already uses this phone number.'));
                }

                if (filled($this->input('email'))) {
                    $emailOwner = Contact::query()->where('workspace_id', $workspaceId)->where('email', $this->input('email'))->value('id');
                    $allowedContactId = $ownContactId ?? $phoneContact?->id;
                    if ($emailOwner && $emailOwner !== $allowedContactId) {
                        $validator->errors()->add('email', __('Another contact already uses this email address.'));
                    }
                }
            },
        ];
    }
}
