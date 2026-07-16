<?php

namespace App\Modules\Languages\Policies;

use App\Modules\Languages\Models\Language;
use Illuminate\Contracts\Auth\Authenticatable;

class LanguagePolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('languages.view');
    }

    public function view(Authenticatable $user, Language $language): bool
    {
        return $user->can('languages.view');
    }

    public function create(Authenticatable $user): bool
    {
        return $user->can('languages.create');
    }

    public function update(Authenticatable $user, Language $language): bool
    {
        return $user->can('languages.edit');
    }

    public function delete(Authenticatable $user, Language $language): bool
    {
        return $user->can('languages.delete');
    }
}
