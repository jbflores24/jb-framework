<?php

declare(strict_types=1);

namespace Jb\Security\detectors;

use Jb\Security\config\SecurityConfig;
use Jb\Security\models\ScoreModel;
use Jb\Security\utils\SecurityRequest;

class NotFoundDetector extends AbstractDetector
{
    public function __construct(private readonly ScoreModel $scores)
    {
    }

    /**
     * Pre-request analysis: nothing to check before the controller runs.
     */
    public function analyze(SecurityRequest $request, SecurityConfig $config): array
    {
        return $this->pass();
    }

    /**
     * Post-response analysis: detect repeated 404 responses (path scanning)
     * for the same IP.
     */
    public function analyzeResponse(SecurityRequest $request, int $statusCode, SecurityConfig $config): array
    {
        if ($statusCode !== 404) {
            return $this->pass();
        }

        $hits = $this->scores->hit('404:' . $request->ip, $request->ip, $request->fingerprint, 300);

        return $hits > (int) $config->get('not_found_max', 30)
            ? $this->block('not_found_scanning', 45, 'medium')
            : $this->pass();
    }
}