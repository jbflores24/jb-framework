<?php

declare(strict_types=1);

namespace Jb\Security\detectors;

use Jb\Security\config\SecurityConfig;
use Jb\Security\utils\SecurityRequest;

class InjectionDetector extends AbstractDetector
{
    /**
     * Detect SQL injection and XSS signatures in request data.
     */
    public function analyze(SecurityRequest $request, SecurityConfig $config): array
    {
        $patterns = [
            '/\bunion\s+select\b/i',
            '/\bor\s+1\s*=\s*1\b/i',
            '/\bdrop\s+table\b/i',
            '/<script\b/i',
            '/javascript:/i',
            '/onerror\s*=/i',
            '/\bbenchmark\s*\(/i',
        ];

        return $this->containsPattern([$request->query, $request->body], $patterns)
            ? $this->block('injection_attempt', 90, 'critical')
            : $this->pass();
    }
}
