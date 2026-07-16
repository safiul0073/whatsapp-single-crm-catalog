<?php

namespace App\Modules\Shared\Support\Tables;

use Closure;

class TableColumn
{
    protected string $type = 'text';

    protected bool $sortable = false;

    protected ?string $sortBy = null;

    protected string $headerClass = '';

    protected string $cellClass = '';

    protected string $alignment = 'left';

    protected string $responsiveClass = '';

    protected ?Closure $valueResolver = null;

    protected ?Closure $formatter = null;

    protected ?string $view = null;

    protected array|Closure $viewData = [];

    protected bool $rawHtml = false;

    protected bool|Closure $visible = true;

    protected array $meta = [];

    protected string|Closure|null $href = null;

    protected bool $openInNewTab = false;

    public function __construct(
        protected string $key,
        protected string $label = ''
    ) {}

    public static function make(string $key, string $label = ''): self
    {
        return new self($key, $label);
    }

    public static function text(string $key, string $label = ''): self
    {
        return (new self($key, $label))->type('text');
    }

    public static function badge(string $key, string $label = ''): self
    {
        return (new self($key, $label))->type('badge');
    }

    public static function booleanBadge(string $key, string $label = ''): self
    {
        return (new self($key, $label))
            ->type('boolean_badge')
            ->meta([
                'true_label' => 'Active',
                'false_label' => 'Inactive',
                'true_variant' => 'success',
                'false_variant' => 'danger',
            ]);
    }

    public static function date(string $key, string $label = ''): self
    {
        return (new self($key, $label))->type('date');
    }

    public static function number(string $key, string $label = ''): self
    {
        return (new self($key, $label))->type('number');
    }

    public static function view(string $key, string $label, string $view): self
    {
        return (new self($key, $label))
            ->type('view')
            ->cellView($view);
    }

    public static function select(string $key = 'id', string $label = ''): self
    {
        return (new self($key, $label))->type('select');
    }

    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function sortable(bool $sortable = true, ?string $sortBy = null): self
    {
        $this->sortable = $sortable;
        $this->sortBy = $sortBy;

        return $this;
    }

    public function headerClass(string $class): self
    {
        $this->headerClass = trim($class);

        return $this;
    }

    public function cellClass(string $class): self
    {
        $this->cellClass = trim($class);

        return $this;
    }

    public function align(string $alignment): self
    {
        $this->alignment = $alignment;

        return $this;
    }

    public function responsive(string $class): self
    {
        $this->responsiveClass = trim($class);

        return $this;
    }

    public function value(Closure $resolver): self
    {
        $this->valueResolver = $resolver;

        return $this;
    }

    public function format(Closure $formatter): self
    {
        $this->formatter = $formatter;

        return $this;
    }

    public function cellView(string $view, array|Closure $data = []): self
    {
        $this->view = $view;
        $this->viewData = $data;

        return $this;
    }

    public function rawHtml(bool $rawHtml = true): self
    {
        $this->rawHtml = $rawHtml;

        return $this;
    }

    public function visible(bool|Closure $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    public function meta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    public function link(string|Closure $href, bool $openInNewTab = false): self
    {
        $this->href = $href;
        $this->openInNewTab = $openInNewTab;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label ?: str($this->key)->headline()->value();
    }

    public function typeName(): string
    {
        return $this->type;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function sortField(): string
    {
        return $this->sortBy ?: $this->key;
    }

    public function headerClasses(): string
    {
        return trim(implode(' ', array_filter([$this->responsiveClass, $this->headerClass, $this->alignmentClass()])));
    }

    public function cellClasses(): string
    {
        return trim(implode(' ', array_filter([$this->responsiveClass, $this->cellClass, $this->cellAlignmentClass()])));
    }

    public function isVisible(mixed $record = null): bool
    {
        if ($this->visible instanceof Closure) {
            return (bool) ($this->visible)($record, $this);
        }

        return (bool) $this->visible;
    }

    public function resolveValue(mixed $record): mixed
    {
        if ($this->valueResolver instanceof Closure) {
            return ($this->valueResolver)($record, $this);
        }

        return data_get($record, $this->key);
    }

    public function formatValue(mixed $record): mixed
    {
        $value = $this->resolveValue($record);

        if ($this->formatter instanceof Closure) {
            return ($this->formatter)($value, $record, $this);
        }

        return match ($this->type) {
            'date' => $value ? format_date($value, true) : '-',
            'number' => $this->formatNumber($value),
            default => $value,
        };
    }

    public function isRawHtml(): bool
    {
        return $this->rawHtml;
    }

    public function viewName(): ?string
    {
        return $this->view;
    }

    public function resolveViewData(mixed $record, mixed $value): array
    {
        if ($this->viewData instanceof Closure) {
            return ($this->viewData)($record, $value, $this);
        }

        return $this->viewData;
    }

    public function badgeConfig(mixed $record): array
    {
        $value = $this->resolveValue($record);

        if ($this->type === 'boolean_badge') {
            return [
                'label' => $value ? __($this->meta['true_label']) : __($this->meta['false_label']),
                'variant' => $value ? $this->meta['true_variant'] : $this->meta['false_variant'],
            ];
        }

        $map = $this->meta['badge_map'] ?? [];
        $resolved = $map[$value] ?? ['label' => $value, 'variant' => 'default'];

        return [
            'label' => $resolved['label'] ?? $value,
            'variant' => $resolved['variant'] ?? 'default',
        ];
    }

    public function dataTh(): string
    {
        return $this->label();
    }

    public function hasLink(): bool
    {
        return $this->href !== null;
    }

    public function resolveHref(mixed $record): string
    {
        if ($this->href instanceof Closure) {
            return (string) ($this->href)($record, $this);
        }

        return (string) ($this->href ?? '');
    }

    public function shouldOpenInNewTab(): bool
    {
        return $this->openInNewTab;
    }

    protected function alignmentClass(): string
    {
        return match ($this->alignment) {
            'right' => 'text-right',
            'center' => 'text-center',
            default => '',
        };
    }

    protected function cellAlignmentClass(): string
    {
        return match ($this->alignment) {
            'right' => 'text-right',
            'center' => 'text-center',
            default => '',
        };
    }

    protected function formatNumber(mixed $value): string
    {
        if (! is_numeric($value)) {
            return (string) $value;
        }

        $decimals = $this->meta['decimals'] ?? 2;

        return number_format((float) $value, $decimals);
    }
}
