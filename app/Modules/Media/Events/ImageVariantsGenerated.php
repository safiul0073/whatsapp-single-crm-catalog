<?php

namespace App\Modules\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ImageVariantsGenerated
{
    use Dispatchable;

    public function __construct(public int $mediaId) {}
}
