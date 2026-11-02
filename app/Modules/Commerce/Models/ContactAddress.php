<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Contacts\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A delivery address saved against a contact for reuse at the POS. */
class ContactAddress extends Model
{
    protected $table = 'commerce_contact_addresses';

    protected $fillable = ['workspace_id', 'contact_id', 'label', 'name', 'phone', 'line1', 'line2', 'city', 'state', 'postal_code', 'country', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
