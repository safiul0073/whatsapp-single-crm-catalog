@php
    $actions = $definition->visibleActions($record);
    $actionMode = $definition->actionsModeName();
    $dropdownId = sprintf('%s-actions-%s', $definition->queryKey(), data_get($record, 'id', spl_object_id($record)));
@endphp

@if($actions !== [])
    <x-tables.actions :type="$actionMode === 'dropdown' ? 'dropdown' : 'inline'" :id="$dropdownId">
        @foreach($actions as $action)
            @if($action->viewName())
                @include($action->viewName(), ['action' => $action, 'record' => $record, 'definition' => $definition])
                @continue
            @endif

            @if($action->typeName() === 'divider')
                <x-tables.action divider />
                @continue
            @endif

            @php
                $label = __($action->resolveLabel($record));
                $icon = $action->resolveIcon($record);
                $variant = $action->resolveVariant($record);
                $attributes = $action->resolveAttributes($record);
                $disabled = $action->isDisabled($record);
                $href = $action->resolveHref($record);
                $method = $action->resolveMethod($record);
            @endphp

            @if($action->typeName() === 'section')
                @if($actionMode === 'dropdown')
                    <div class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-neutral-400">
                        {{ $label }}
                    </div>
                @endif
                @continue
            @endif

            @php
                $resolvedAttributes = match ($action->typeName()) {
                    'delete' => array_merge($attributes, [
                        'data-modal-trigger' => 'globalConfirmModal',
                        'data-confirm-title' => $action->resolveConfirmTitle($record) ?: __('Delete Record?'),
                        'data-confirm-message' => $action->resolveConfirmMessage($record) ?: __('This action cannot be undone.'),
                        'data-confirm-action' => $href,
                        'data-confirm-method' => $method ?: 'DELETE',
                        'data-confirm-button' => __('Yes, Delete'),
                    ]),
                    'toggle_status' => array_merge($attributes, [
                        'data-modal-trigger' => 'globalConfirmModal',
                        'data-confirm-title' => $action->resolveConfirmTitle($record) ?: $label,
                        'data-confirm-message' => $action->resolveConfirmMessage($record) ?: __('Are you sure you want to continue?'),
                        'data-confirm-action' => $href,
                        'data-confirm-method' => $method ?: 'POST',
                        'data-confirm-button' => $label,
                    ]),
                    'submit' => array_merge($attributes, [
                        'data-submit-action' => $href,
                        'data-submit-method' => $method,
                    ]),
                    'modal' => array_merge($attributes, [
                        'data-modal-trigger' => $action->resolveModal($record),
                    ]),
                    default => $attributes,
                };
            @endphp

            @if($action->typeName() === 'link' && $href)
                @php
                    $classes = $actionMode === 'dropdown' ? 'floating-dropdown-item' : 'btn-icon h-9 w-9';
                    if ($variant === 'danger') {
                        $classes .= $actionMode === 'dropdown' ? ' text-error hover:bg-error/10' : ' text-error';
                    }
                @endphp

                <a
                    href="{{ $href }}"
                    class="{{ $classes }}"
                    title="{{ $label }}"
                    @if($disabled) aria-disabled="true" @endif
                    @foreach($resolvedAttributes as $attribute => $value)
                        @if(is_bool($value))
                            @if($value)
                                {{ $attribute }}
                            @endif
                        @else
                            {{ $attribute }}="{{ $value }}"
                        @endif
                    @endforeach
                >
                    @if($icon)
                        <i class="ph ph-{{ $icon }}{{ $actionMode === 'dropdown' ? ' text-lg' : '' }}"></i>
                    @endif
                    @if($actionMode === 'dropdown')
                        <span>{{ $label }}</span>
                    @endif
                </a>
            @else
                @php
                    $classes = $actionMode === 'dropdown' ? 'floating-dropdown-item' : 'btn-icon h-9 w-9';
                    if ($variant === 'danger') {
                        $classes .= $actionMode === 'dropdown' ? ' text-error hover:bg-error/10' : ' text-error';
                    }
                @endphp

                <button
                    type="button"
                    class="{{ $classes }}"
                    title="{{ $label }}"
                    @if($disabled) disabled @endif
                    @foreach($resolvedAttributes as $attribute => $value)
                        @if(is_bool($value))
                            @if($value)
                                {{ $attribute }}
                            @endif
                        @else
                            {{ $attribute }}="{{ $value }}"
                        @endif
                    @endforeach
                >
                    @if($icon)
                        <i class="ph ph-{{ $icon }}{{ $actionMode === 'dropdown' ? ' text-lg' : '' }}"></i>
                    @endif
                    @if($actionMode === 'dropdown')
                        <span>{{ $label }}</span>
                    @endif
                </button>
            @endif
        @endforeach
    </x-tables.actions>
@endif
