<?php

namespace App\Modules\Blogs\Http\Requests;

class UpdateBlogCategoryRequest extends BlogCategoryRequest
{
    public function rules(): array
    {
        return $this->sharedRules();
    }
}
