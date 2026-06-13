<?php

declare(strict_types=1);

namespace Jb\Security\detectors;

use Jb\Security\config\SecurityConfig;
use Jb\Security\utils\SecurityRequest;

class MethodDetector extends AbstractDetector
{
    /**
     * Detect HTTP methods outside the configured allow list.
     */
    public function analyze(SecurityRequest $request, SecurityConfig $config): array
    {
        $allowed = $config->get('allowed_methods', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']);

        return in_array($request->method, $allowed, true)
            ? $this->pass()
            : $this->block('method_not_allowed', 40, 'medium');
    }
}
