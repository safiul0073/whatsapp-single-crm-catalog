<?php

namespace App\Modules\Blogs\Http\Requests;

class UpdateBlogRequest extends BlogRequest
{
    public function rules(): array
    {
        return $this->sharedRules();
    }
}
