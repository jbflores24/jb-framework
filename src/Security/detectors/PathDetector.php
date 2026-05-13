<?php

declare(strict_types=1);

namespace Jb\Security\detectors;

use Jb\Security\config\SecurityConfig;
use Jb\Security\utils\SecurityRequest;

class PathDetector extends AbstractDetector
{
    /**
     * Detect traversal and sensitive file probing.
     */
    public function analyze(SecurityRequest $request, SecurityConfig $config): array
    {
        $patterns = [
            '/\.\./',
            '/%2e%2e/i',
            '/\/(etc\/passwd|proc\/self|windows\/win\.ini)/i',
            '/\.(env|git|svn|bak|sql|ini)(\/|$)/i',
        ];

        return $this->containsPattern($request->path, $patterns)
            ? $this->block('path_traversal', 85, 'critical')
            : $this->pass();
    }
}
