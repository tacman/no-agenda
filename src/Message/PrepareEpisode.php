<?php

namespace App\Message;

class PrepareEpisode implements \Stringable
{
    public function __construct(
        public readonly string $episodeCode,
    ) {}

    public function __toString(): string
    {
        return "Prepare episode $this->episodeCode";
    }
}
