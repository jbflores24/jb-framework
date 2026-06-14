<?php

declare(strict_types=1);

namespace Jb\Security\detectors;

use Jb\Security\config\SecurityConfig;
use Jb\Security\utils\SecurityRequest;

abstract class AbstractDetector
{
    /** @return array{blocked: bool, reason: string, score: int, severity: string} */
    protected function pass(): array
    {
        return ['blocked' => false, 'reason' => 'ok', 'score' => 0, 'severity' => 'info'];
    }

    /** @return array{blocked: bool, reason: string, score: int, severity: string} */
    protected function block(string $reason, int $score, string $severity = 'high'): array
    {
        return ['blocked' => true, 'reason' => $reason, 'score' => $score, 'severity' => $severity];
    }

    /**
     * Post-response analysis hook. Detectors that only need pre-request
     * analysis can ignore this; the default implementation is a no-op pass.
     */
    public function analyzeResponse(SecurityRequest $request, int $statusCode, SecurityConfig $config): array
    {
        return $this->pass();
    }

    protected function containsPattern(mixed $value, array $patterns): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->containsPattern($item, $patterns)) {
                    return true;
                }
            }

            return false;
        }

        $text = strtolower((string) $value);
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
