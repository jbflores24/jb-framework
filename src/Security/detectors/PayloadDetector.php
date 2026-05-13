<?php

declare(strict_types=1);

namespace Jb\Security\detectors;

use Jb\Security\config\SecurityConfig;
use Jb\Security\utils\SecurityRequest;

class PayloadDetector extends AbstractDetector
{
    /**
     * Detect oversized payloads and suspicious encoded blobs.
     */
    public function analyze(SecurityRequest $request, SecurityConfig $config): array
    {
        $maxBytes = (int) $config->get('max_payload_bytes', 1048576);
        if ($request->contentLength > $maxBytes) {
            return $this->block('payload_too_large', 60, 'high');
        }

        $patterns = ['/[A-Za-z0-9+\/]{800,}={0,2}/', '/%00/', '/\x00/'];

        return $this->containsPattern($request->body, $patterns)
            ? $this->block('suspicious_payload', 55, 'medium')
            : $this->pass();
    }
}
