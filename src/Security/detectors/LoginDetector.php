<?php

declare(strict_types=1);

namespace Jb\Security\detectors;

use Jb\Security\config\SecurityConfig;
use Jb\Security\models\ScoreModel;
use Jb\Security\utils\SecurityRequest;

class LoginDetector extends AbstractDetector
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
     * Post-response analysis: detect repeated failed login attempts (401/403)
     * on endpoints whose path contains "login".
     */
    public function analyzeResponse(SecurityRequest $request, int $statusCode, SecurityConfig $config): array
    {
        if ($request->method !== 'POST' || !str_contains($request->path, 'login')) {
            return $this->pass();
        }

        if ($statusCode !== 401 && $statusCode !== 403) {
            return $this->pass();
        }

        $hits = $this->scores->hit('login:' . $request->ip, $request->ip, $request->fingerprint, 900);

        return $hits >= (int) $config->get('login_max_failed', 5)
            ? $this->block('login_bruteforce', 75, 'high')
            : $this->pass();
    }
}