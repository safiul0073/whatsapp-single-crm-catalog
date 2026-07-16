<?php

namespace App\Modules\Shared\Support\Tables;

use Closure;

class TableBulkAction
{
    protected string|Closure $deleteAction = '';

    protected string|Closure $toggleAction = '';

    protected string|Closure $exportAction = '';

    protected ?string $view = null;

    protected array|Closure $viewData = [];

    public function __construct(
        protected string $group
    ) {}

    public static function make(string $group): self
    {
        return new self($group);
    }

    public function deleteAction(string|Closure $action): self
    {
        $this->deleteAction = $action;

        return $this;
    }

    public function toggleAction(string|Closure $action): self
    {
        $this->toggleAction = $action;

        return $this;
    }

    public function exportAction(string|Closure $action): self
    {
        $this->exportAction = $action;

        return $this;
    }

    public function view(string $view, array|Closure $data = []): self
    {
        $this->view = $view;
        $this->viewData = $data;

        return $this;
    }

    public function group(): string
    {
        return $this->group;
    }

    public function resolveDeleteAction(mixed $items = null, ?TableDefinition $definition = null): string
    {
        return $this->resolve($this->deleteAction, $items, $definition);
    }

    public function resolveToggleAction(mixed $items = null, ?TableDefinition $definition = null): string
    {
        return $this->resolve($this->toggleAction, $items, $definition);
    }

    public function resolveExportAction(mixed $items = null, ?TableDefinition $definition = null): string
    {
        return $this->resolve($this->exportAction, $items, $definition);
    }

    public function viewName(): ?string
    {
        return $this->view;
    }

    public function resolveViewData(mixed $items = null, ?TableDefinition $definition = null): array
    {
        if ($this->viewData instanceof Closure) {
            return ($this->viewData)($items, $definition);
        }

        return $this->viewData;
    }

    protected function resolve(string|Closure $value, mixed $items = null, ?TableDefinition $definition = null): string
    {
        if ($value instanceof Closure) {
            return (string) $value($items, $definition);
        }

        return $value;
    }
}
