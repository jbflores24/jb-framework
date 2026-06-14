<?php

declare(strict_types=1);

namespace Jb\Security\detectors;

use Jb\Security\config\SecurityConfig;
use Jb\Security\models\ScoreModel;
use Jb\Security\utils\SecurityRequest;

class RateLimitDetector extends AbstractDetector
{
    public function __construct(private readonly ScoreModel $scores)
    {
    }

    /**
     * Detect excessive request count per IP and window.
     */
    public function analyze(SecurityRequest $request, SecurityConfig $config): array
    {
        $window = (int) $config->get('rate_window_seconds', 60);
        $max = (int) $config->get('rate_max_requests', 120);
        $hits = $this->scores->hit($request->ip, $request->ip, $request->fingerprint, $window);

        return $hits > $max ? $this->block('rate_limit_exceeded', 50, 'medium') : $this->pass();
    }
}
