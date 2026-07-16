<?php

namespace App\Modules\Blogs\Http\Requests;

class StoreBlogCategoryRequest extends BlogCategoryRequest
{
    public function rules(): array
    {
        return $this->sharedRules();
    }
}
