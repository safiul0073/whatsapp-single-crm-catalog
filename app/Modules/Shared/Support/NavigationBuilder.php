<?php

namespace App\Modules\Shared\Support;

class NavigationBuilder
{
    protected string $currentGroup = 'Main Menu';

    protected ?int $currentItemIndex = null;

    protected array $items = [];

    public function group(string $group): self
    {
        $this->currentGroup = $group;
        $this->currentItemIndex = null;

        return $this;
    }

    public function item(
        string $label,
        string $route,
        ?string $icon = null,
        ?string $permission = null,
        ?int $order = null,
        array $children = []
    ): self {
        $this->items[] = [
            'label' => $label,
            'icon' => $icon ?? 'ph-cube',
            'route' => $route,
            'group' => $this->currentGroup,
            'permission' => $permission,
            'children' => array_values($children),
            'order' => $order ?? 9999,
            '__seq' => count($this->items),
        ];

        $this->currentItemIndex = array_key_last($this->items);

        return $this;
    }

    public function icon(string $icon): self
    {
        return $this->mutateCurrentItem(fn (array $item): array => array_merge($item, ['icon' => $icon]));
    }

    public function permission(?string $permission): self
    {
        return $this->mutateCurrentItem(fn (array $item): array => array_merge($item, ['permission' => $permission]));
    }

    public function order(int $order): self
    {
        return $this->mutateCurrentItem(fn (array $item): array => array_merge($item, ['order' => $order]));
    }

    public function children(array $children): self
    {
        return $this->mutateCurrentItem(fn (array $item): array => array_merge($item, ['children' => array_values($children)]));
    }

    public function child(string $label, string $route): self
    {
        return $this->mutateCurrentItem(function (array $item) use ($label, $route): array {
            $item['children'][] = ['label' => $label, 'route' => $route];

            return $item;
        });
    }

    public function toArray(): array
    {
        $items = $this->items;

        usort($items, function (array $left, array $right): int {
            $order = $left['order'] <=> $right['order'];

            if ($order !== 0) {
                return $order;
            }

            return $left['__seq'] <=> $right['__seq'];
        });

        return array_map(function (array $item): array {
            unset($item['__seq']);

            return $item;
        }, $items);
    }

    protected function mutateCurrentItem(callable $callback): self
    {
        if ($this->currentItemIndex === null || ! isset($this->items[$this->currentItemIndex])) {
            return $this;
        }

        $this->items[$this->currentItemIndex] = $callback($this->items[$this->currentItemIndex]);

        return $this;
    }
}
