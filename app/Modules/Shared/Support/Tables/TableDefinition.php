<?php

namespace App\Modules\Shared\Support\Tables;

class TableDefinition
{
    /** @var array<int, TableColumn> */
    protected array $columns = [];

    /** @var array<int, TableAction> */
    protected array $actions = [];

    protected ?TableBulkAction $bulkActions = null;

    protected ?TableFilters $filters = null;

    protected string $title = '';

    protected string $description = '';

    protected string $emptyMessage = 'No records found.';

    protected string $searchPlaceholder = 'Search...';

    protected bool $searchable = true;

    protected array $perPageOptions = [10, 15, 25, 50];

    protected string $exportUrl = '';

    protected ?string $rowView = null;

    protected ?string $headerView = null;

    protected string $wrapperClass = '';

    protected string $tableClass = '';

    protected array $wrapperAttributes = [];

    protected array $tableAttributes = [];

    protected string $actionsMode = 'inline';

    protected string $actionsLabel = 'Actions';

    protected string $actionsHeaderClass = 'text-right';

    protected string $actionsCellClass = 'text-right';

    public function __construct(
        protected string $queryKey
    ) {}

    public static function make(string $queryKey): self
    {
        return new self($queryKey);
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function emptyMessage(string $message): self
    {
        $this->emptyMessage = $message;

        return $this;
    }

    public function searchPlaceholder(string $placeholder): self
    {
        $this->searchPlaceholder = $placeholder;

        return $this;
    }

    public function searchable(bool $searchable = true): self
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function perPageOptions(array $options): self
    {
        $this->perPageOptions = $options;

        return $this;
    }

    public function exportUrl(string $url): self
    {
        $this->exportUrl = $url;

        return $this;
    }

    public function filters(TableFilters $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    public function columns(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    public function column(TableColumn $column): self
    {
        $this->columns[] = $column;

        return $this;
    }

    public function actions(array $actions): self
    {
        $this->actions = $actions;

        return $this;
    }

    public function action(TableAction $action): self
    {
        $this->actions[] = $action;

        return $this;
    }

    public function bulkActions(TableBulkAction $bulkActions): self
    {
        $this->bulkActions = $bulkActions;

        return $this;
    }

    public function rowView(string $view): self
    {
        $this->rowView = $view;

        return $this;
    }

    public function headerView(string $view): self
    {
        $this->headerView = $view;

        return $this;
    }

    public function wrapperClass(string $class): self
    {
        $this->wrapperClass = trim($class);

        return $this;
    }

    public function tableClass(string $class): self
    {
        $this->tableClass = trim($class);

        return $this;
    }

    public function wrapperAttributes(array $attributes): self
    {
        $this->wrapperAttributes = $attributes;

        return $this;
    }

    public function tableAttributes(array $attributes): self
    {
        $this->tableAttributes = $attributes;

        return $this;
    }

    public function actionsMode(string $mode): self
    {
        $this->actionsMode = $mode;

        return $this;
    }

    public function actionsLabel(string $label): self
    {
        $this->actionsLabel = $label;

        return $this;
    }

    public function actionsHeaderClass(string $class): self
    {
        $this->actionsHeaderClass = trim($class);

        return $this;
    }

    public function actionsCellClass(string $class): self
    {
        $this->actionsCellClass = trim($class);

        return $this;
    }

    public function queryKey(): string
    {
        return $this->queryKey;
    }

    public function titleText(): string
    {
        return $this->title;
    }

    public function descriptionText(): string
    {
        return $this->description;
    }

    public function emptyMessageText(): string
    {
        return $this->emptyMessage;
    }

    public function searchPlaceholderText(): string
    {
        return $this->searchPlaceholder;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function perPageOptionValues(): array
    {
        return $this->perPageOptions;
    }

    public function exportUrlValue(): string
    {
        return $this->exportUrl;
    }

    public function filtersConfig(): ?TableFilters
    {
        return $this->filters;
    }

    /** @return array<int, TableColumn> */
    public function columnsList(): array
    {
        return $this->columns;
    }

    /** @return array<int, TableColumn> */
    public function visibleColumns(mixed $record = null): array
    {
        return array_values(array_filter(
            $this->columns,
            fn (TableColumn $column): bool => $column->isVisible($record)
        ));
    }

    /** @return array<int, TableAction> */
    public function actionsList(): array
    {
        return $this->actions;
    }

    /** @return array<int, TableAction> */
    public function visibleActions(mixed $record): array
    {
        return array_values(array_filter(
            $this->actions,
            fn (TableAction $action): bool => $action->isVisible($record)
        ));
    }

    public function bulkActionsConfig(): ?TableBulkAction
    {
        return $this->bulkActions;
    }

    public function rowViewName(): ?string
    {
        return $this->rowView;
    }

    public function headerViewName(): ?string
    {
        return $this->headerView;
    }

    public function wrapperClasses(): string
    {
        return $this->wrapperClass;
    }

    public function tableClasses(): string
    {
        return $this->tableClass;
    }

    public function wrapperAttributeBag(): array
    {
        return $this->wrapperAttributes;
    }

    public function tableAttributeBag(): array
    {
        return $this->tableAttributes;
    }

    public function actionsModeName(): string
    {
        return $this->actionsMode;
    }

    public function actionsLabelText(): string
    {
        return $this->actionsLabel;
    }

    public function actionsHeaderClasses(): string
    {
        return $this->actionsHeaderClass;
    }

    public function actionsCellClasses(): string
    {
        return $this->actionsCellClass;
    }

    public function hasActions(): bool
    {
        return $this->actions !== [];
    }

    public function colspan(): int
    {
        return count($this->visibleColumns()) + ($this->hasActions() ? 1 : 0);
    }
}
