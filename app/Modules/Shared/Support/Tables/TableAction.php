<?php

namespace App\Modules\Shared\Support\Tables;

use Closure;

class TableAction
{
    protected string $type = 'link';

    protected string|Closure $label;

    protected string|Closure $icon = '';

    protected string|Closure $variant = 'default';

    protected string|Closure $href = '';

    protected string|Closure $modal = '';

    protected string|Closure $method = 'POST';

    protected string|Closure $confirmTitle = '';

    protected string|Closure $confirmMessage = '';

    protected bool|Closure $visible = true;

    protected bool|Closure $disabled = false;

    protected array|Closure $attributes = [];

    protected ?string $view = null;

    protected string $stateField = 'is_active';

    protected string|Closure $activeLabel = 'Deactivate';

    protected string|Closure $inactiveLabel = 'Activate';

    public function __construct(
        protected string $name
    ) {
        $this->label = $name;
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    public static function link(string $name, string|Closure $href = '', string|Closure|null $label = null): self
    {
        return (new self($name))
            ->type('link')
            ->href($href)
            ->label($label ?? $name);
    }

    public static function button(string $name, string|Closure|null $label = null): self
    {
        return (new self($name))
            ->type('button')
            ->label($label ?? $name);
    }

    public static function submit(string $name, string|Closure $href = '', string|Closure|null $label = null): self
    {
        return (new self($name))
            ->type('submit')
            ->href($href)
            ->label($label ?? $name);
    }

    public static function modal(string $name, string|Closure $modal, string|Closure|null $label = null): self
    {
        return (new self($name))
            ->type('modal')
            ->modalId($modal)
            ->label($label ?? $name);
    }

    public static function delete(string $name = 'delete', string|Closure|null $href = '', string|Closure|null $label = null): self
    {
        return (new self($name))
            ->type('delete')
            ->href($href)
            ->method('DELETE')
            ->label($label ?? 'Delete')
            ->variant('danger');
    }

    public static function toggleStatus(string|Closure $href, string $stateField = 'is_active'): self
    {
        return (new self('toggle_status'))
            ->type('toggle_status')
            ->href($href)
            ->method('POST')
            ->stateField($stateField)
            ->label(fn ($record, self $action) => $action->resolveToggleLabel($record));
    }

    public static function divider(): self
    {
        return (new self('divider'))->type('divider');
    }

    public static function section(string|Closure $label): self
    {
        return (new self('section'))
            ->type('section')
            ->label($label);
    }

    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function label(string|Closure $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function icon(string|Closure $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function variant(string|Closure $variant): self
    {
        $this->variant = $variant;

        return $this;
    }

    public function href(string|Closure $href): self
    {
        $this->href = $href;

        return $this;
    }

    public function modalId(string|Closure $modal): self
    {
        $this->modal = $modal;

        return $this;
    }

    public function method(string|Closure $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function confirmTitle(string|Closure $title): self
    {
        $this->confirmTitle = $title;

        return $this;
    }

    public function confirmMessage(string|Closure $message): self
    {
        $this->confirmMessage = $message;

        return $this;
    }

    public function visible(bool|Closure $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    public function disabled(bool|Closure $disabled): self
    {
        $this->disabled = $disabled;

        return $this;
    }

    public function attributes(array|Closure $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function view(string $view): self
    {
        $this->view = $view;

        return $this;
    }

    public function stateField(string $stateField): self
    {
        $this->stateField = $stateField;

        return $this;
    }

    public function activeLabel(string|Closure $label): self
    {
        $this->activeLabel = $label;

        return $this;
    }

    public function inactiveLabel(string|Closure $label): self
    {
        $this->inactiveLabel = $label;

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function typeName(): string
    {
        return $this->type;
    }

    public function resolveLabel(mixed $record): string
    {
        return (string) $this->resolve($this->label, $record);
    }

    public function resolveIcon(mixed $record): string
    {
        return (string) $this->resolve($this->icon, $record);
    }

    public function resolveVariant(mixed $record): string
    {
        return (string) $this->resolve($this->variant, $record);
    }

    public function resolveHref(mixed $record): string
    {
        return (string) $this->resolve($this->href, $record);
    }

    public function resolveModal(mixed $record): string
    {
        return (string) $this->resolve($this->modal, $record);
    }

    public function resolveMethod(mixed $record): string
    {
        return strtoupper((string) $this->resolve($this->method, $record));
    }

    public function resolveConfirmTitle(mixed $record): string
    {
        return (string) $this->resolve($this->confirmTitle, $record);
    }

    public function resolveConfirmMessage(mixed $record): string
    {
        return (string) $this->resolve($this->confirmMessage, $record);
    }

    public function resolveAttributes(mixed $record): array
    {
        $attributes = $this->resolve($this->attributes, $record);

        return is_array($attributes) ? $attributes : [];
    }

    public function isVisible(mixed $record): bool
    {
        return (bool) $this->resolve($this->visible, $record);
    }

    public function isDisabled(mixed $record): bool
    {
        return (bool) $this->resolve($this->disabled, $record);
    }

    public function viewName(): ?string
    {
        return $this->view;
    }

    public function resolveToggleLabel(mixed $record): string
    {
        $isActive = (bool) data_get($record, $this->stateField, false);
        $label = $isActive ? $this->activeLabel : $this->inactiveLabel;

        return (string) $this->resolve($label, $record);
    }

    protected function resolve(mixed $value, mixed $record): mixed
    {
        if ($value instanceof Closure) {
            return $value($record, $this);
        }

        return $value;
    }
}
