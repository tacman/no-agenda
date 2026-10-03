<?php

declare(strict_types=1);

namespace App\Ai;

final readonly class DenseSummaryResult implements \JsonSerializable
{
    public function __construct(public string $dense_summary) {}

    public function jsonSerialize(): array
    {
        return ['dense_summary' => $this->dense_summary];
    }
}
