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
     * Detect repeated failed login signals sent by auth controllers.
     */
    public function analyze(SecurityRequest $request, SecurityConfig $config): array
    {
        if (!str_contains($request->path, 'login') || ($request->body['failed'] ?? false) !== true) {
            return $this->pass();
        }

        $hits = $this->scores->hit('login:' . $request->ip, $request->fingerprint, 900);

        return $hits >= (int) $config->get('login_max_failed', 5)
            ? $this->block('login_bruteforce', 75, 'high')
            : $this->pass();
    }
}
