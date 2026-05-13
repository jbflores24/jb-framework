<?php

declare(strict_types=1);

namespace Jb\Security\detectors;

use Jb\Security\config\SecurityConfig;
use Jb\Security\utils\SecurityRequest;

class SessionDetector extends AbstractDetector
{
    /**
     * Detect authenticated sessions with suspicious fingerprint changes.
     */
    public function analyze(SecurityRequest $request, SecurityConfig $config): array
    {
        $expected = $request->body['_fingerprint'] ?? null;
        if ($request->userId === null || $expected === null) {
            return $this->pass();
        }

        return hash_equals((string) $expected, $request->fingerprint)
            ? $this->pass()
            : $this->block('session_fingerprint_changed', 65, 'high');
    }
}
