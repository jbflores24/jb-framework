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
     * Detect repeated not-found probes when controllers mark the request.
     */
    public function analyze(SecurityRequest $request, SecurityConfig $config): array
    {
        if (($request->body['_security_status'] ?? null) !== 404) {
            return $this->pass();
        }

        $hits = $this->scores->hit('404:' . $request->ip, $request->ip, $request->fingerprint, 300);

        return $hits > (int) $config->get('not_found_max', 30)
            ? $this->block('not_found_scanning', 45, 'medium')
            : $this->pass();
    }
}
