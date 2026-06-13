<?php

declare(strict_types=1);

namespace Jb\Security\detectors;

use Jb\Security\config\SecurityConfig;
use Jb\Security\utils\SecurityRequest;

class BotDetector extends AbstractDetector
{
    /**
     * Detect common scanners and suspicious empty agents.
     */
    public function analyze(SecurityRequest $request, SecurityConfig $config): array
    {
        $ua = strtolower((string) $request->userAgent);
        $allowEmpty = (bool) $config->get('allow_empty_user_agent', false);

        if ($ua === '' && !$allowEmpty) {
            return $this->block('empty_user_agent', 25, 'low');
        }

        $patterns = ['/sqlmap/', '/nikto/', '/nessus/', '/acunetix/', '/nmap/', '/dirbuster/', '/masscan/', '/zgrab/'];

        return $this->containsPattern($ua, $patterns)
            ? $this->block('scanner_detected', 95, 'critical')
            : $this->pass();
    }
}
