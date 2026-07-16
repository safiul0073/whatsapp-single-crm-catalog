<?php

namespace App\Modules\Shared\Contracts;

interface DashboardWidget
{
    /**
     * Unique widget identifier.
     */
    public function id(): string;

    /**
     * Display title for the widget.
     */
    public function title(): string;

    /**
     * Render the widget HTML.
     */
    public function render(): string;

    /**
     * Sort order (lower number = renders first).
     */
    public function position(): int;

    /**
     * Widget width: 'full', 'half', or 'quarter'.
     */
    public function width(): string;

    /**
     * Required permission to view (null = visible to all authenticated users).
     */
    public function permission(): ?string;

    /**
     * Target panel: 'admin', 'user', or 'all'.
     */
    public function panel(): string;

    /**
     * Whether the widget should render. Use for conditional display
     * (e.g., only show if there is data to display).
     */
    public function shouldRender(): bool;

    /**
     * Cache duration in seconds. Return null to disable caching.
     */
    public function cacheFor(): ?int;
}
