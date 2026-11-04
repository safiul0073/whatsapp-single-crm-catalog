<?php

namespace App\Modules\MarketingChannels\Services\ChannelReadiness;

/**
 * One checklist row. States:
 * - done: finished
 * - waiting: Meta is reviewing, nothing to do yet
 * - action: the business has to do something
 * - blocked: the feature cannot work until this is fixed
 * - optional: recommended, not required
 * - unknown: Meta did not answer, so it could not be checked
 */
final class ReadinessItem
{
    public const STATES = ['done', 'waiting', 'action', 'blocked', 'optional', 'unknown'];

    public const FEATURES = ['receive', 'send', 'templates', 'campaigns', 'publish'];

    /**
     * @param  array<int, string>  $how  Numbered steps to finish the item.
     * @param  array<int, string>  $blocks  Features that do not work until it is done.
     * @return array{key: string, label: string, state: string, why: string, how: array<int, string>, link: string|null, link_label: string|null, blocks: array<int, string>, detail: string|null}
     */
    public static function make(
        string $key,
        string $label,
        string $state,
        string $why,
        array $how = [],
        ?string $link = null,
        array $blocks = [],
        ?string $detail = null,
        ?string $linkLabel = null,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'state' => in_array($state, self::STATES, true) ? $state : 'unknown',
            'why' => $why,
            'how' => array_values(array_filter($how, 'filled')),
            'link' => $link,
            'link_label' => $link ? ($linkLabel ?? __('Open in Meta')) : null,
            'blocks' => $state === 'done' || $state === 'optional' ? [] : array_values(array_intersect($blocks, self::FEATURES)),
            'detail' => filled($detail) ? $detail : null,
        ];
    }
}
