<?php

namespace App\Modules\Campaigns\Services;

use App\Modules\Segments\Models\Segment;
use App\Modules\Segments\Services\SegmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SegmentQueryService
{
    public function __construct(protected SegmentService $segments) {}

    public function contacts(Segment $segment): Collection
    {
        return $this->query($segment)->get();
    }

    public function query(Segment $segment): Builder
    {
        return $this->segments->query($segment);
    }

    public function count(Segment $segment): int
    {
        return $this->query($segment)->count();
    }
}
