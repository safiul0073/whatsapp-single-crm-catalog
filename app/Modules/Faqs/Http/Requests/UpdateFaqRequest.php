<?php

namespace App\Modules\Faqs\Http\Requests;

class UpdateFaqRequest extends FaqRequest
{
    public function rules(): array
    {
        return $this->sharedRules();
    }
}
