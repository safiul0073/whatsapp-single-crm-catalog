<?php

namespace App\Modules\Faqs\Services;

use App\Modules\Faqs\Models\Faq;
use App\Modules\Shared\Traits\HasCrudOperations;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class FaqsService
{
    use HasCrudOperations;

    protected string $model = Faq::class;

    /** @var array<string> */
    protected array $searchable = ['question', 'answer'];

    /** @var array<string> */
    protected array $filterable = ['active', 'status'];

    /** @var array<string> */
    protected array $sortable = ['question', 'sort_order', 'status', 'created_at'];

    protected string $defaultSortBy = 'sort_order';

    protected string $defaultSortOrder = 'asc';

    public function create(array $data): Model
    {
        return ($this->model)::create($this->payload($data));
    }

    public function update(Model $record, array $data): Model
    {
        $record->update($this->payload($data, $record));

        return $record->fresh();
    }

    public function toggleStatus(Model $record): Model
    {
        $record->update(['active' => ! $record->active]);

        return $record->fresh();
    }

    /** @return Collection<int, Faq> */
    public function publishedFaqs(): Collection
    {
        return Faq::query()
            ->active()
            ->published()
            ->orderBy('sort_order')
            ->orderBy('question')
            ->get();
    }

    protected function payload(array $data, ?Model $record = null): array
    {
        $status = $data['status'];
        $publishedAt = $record?->published_at;

        if ($status === 'published' && ! $publishedAt) {
            $publishedAt = now();
        }

        if ($status !== 'published') {
            $publishedAt = null;
        }

        return [
            'question' => $data['question'],
            'answer' => $data['answer'],
            'sort_order' => $data['sort_order'],
            'active' => (bool) ($data['active'] ?? true),
            'status' => $status,
            'published_at' => $publishedAt,
        ];
    }
}
