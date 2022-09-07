<?php

declare(strict_types=1);

namespace Opekunov\Centrifugo\Contracts;

use Carbon\Carbon;

interface CentrifugoInterface
{
    public function publish(string $channel, array $data): array;

    public function broadcast(array $channels, array $data): array;

    public function presence(string $channel): array;

    public function presenceStats(string $channel): array;

    public function history(string $channel): array;

    public function historyRemove(string $channel): array;

    public function unsubscribe(string $channel, string $user): array;

    public function disconnect(string $userId): array;

    public function channels(): array;

    public function info(): array;

    public function generateSubscriptionToken(
        string|int $userId,
        string $channel,
        int|Carbon $exp = 0,
        array $info = [],
        array $override = []
    ): string;

    public function generateConnectionToken(
        string|int $userId,
        int|Carbon $exp = 0,
        array $info = [],
    ): string;

    public function showNodeInfo(): bool;
}
