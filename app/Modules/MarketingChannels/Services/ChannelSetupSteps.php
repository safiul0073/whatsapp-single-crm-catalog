<?php

namespace App\Modules\MarketingChannels\Services;

/**
 * Turns a page's step definitions into a renderable list: the first step that is neither
 * done nor skipped becomes the current one and every later unfinished step is pending.
 */
final class ChannelSetupSteps
{
    /**
     * @param  array<int, array{key: string, label: string, description: string, done: bool, skipped?: bool}>  $steps
     * @return array<int, array{key: string, label: string, description: string, state: string}>
     */
    public static function build(array $steps): array
    {
        $hasCurrent = false;

        return array_map(function (array $step) use (&$hasCurrent): array {
            $state = match (true) {
                (bool) ($step['skipped'] ?? false) => 'skipped',
                (bool) ($step['done'] ?? false) => 'done',
                ! $hasCurrent => 'current',
                default => 'pending',
            };

            if ($state === 'current') {
                $hasCurrent = true;
            }

            return [
                'key' => $step['key'],
                'label' => $step['label'],
                'description' => $step['description'],
                'state' => $state,
            ];
        }, array_values($steps));
    }

    /**
     * @param  array<int, array{state: string}>  $steps
     */
    public static function isComplete(array $steps): bool
    {
        return collect($steps)->every(fn (array $step): bool => in_array($step['state'], ['done', 'skipped'], true));
    }
}
