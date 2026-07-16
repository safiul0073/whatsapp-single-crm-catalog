<?php

namespace App\Modules\Shared\Support\Tables;

use Closure;

class TableFilters
{
    protected array|Closure $data = [];

    public function __construct(
        protected string $view
    ) {}

    public static function view(string $view, array|Closure $data = []): self
    {
        return (new self($view))->data($data);
    }

    public function data(array|Closure $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function viewName(): string
    {
        return $this->view;
    }

    public function resolveData(mixed $items = null, ?TableDefinition $definition = null): array
    {
        if ($this->data instanceof Closure) {
            return ($this->data)($items, $definition);
        }

        return $this->data;
    }
}
