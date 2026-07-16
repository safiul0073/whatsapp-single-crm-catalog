<?php

namespace App\Modules\Faqs\Http\Requests;

class StoreFaqRequest extends FaqRequest
{
    public function rules(): array
    {
        return $this->sharedRules();
    }
}
