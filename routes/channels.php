<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('store.{workspaceId}.live', function (): bool {
    return true;
});

Broadcast::channel('live.{streamId}', function (): bool {
    return true;
});
