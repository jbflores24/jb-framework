<?php

declare(strict_types=1);

namespace Jb\Security\services;

use Jb\Security\config\SecurityConfig;

class ScoringEngine
{
    public function __construct(private readonly SecurityConfig $config)
    {
    }

    /**
     * Determine block duration by score.
     */
    public function blockMinutes(int $score): int
    {
        if ($score >= 90) {
            return (int) $this->config->get('block_critical_minutes', 1440);
        }

        if ($score >= 70) {
            return (int) $this->config->get('block_high_minutes', 240);
        }

        return (int) $this->config->get('block_default_minutes', 60);
    }
}
