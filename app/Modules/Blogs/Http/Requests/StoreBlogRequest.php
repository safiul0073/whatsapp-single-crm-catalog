<?php

namespace App\Modules\Blogs\Http\Requests;

class StoreBlogRequest extends BlogRequest
{
    public function rules(): array
    {
        return $this->sharedRules();
    }
}
