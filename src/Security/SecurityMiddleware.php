<?php

declare(strict_types=1);

namespace Jb\Security;

use Closure;
use Jb\Core\HttpException;
use Jb\Core\Request;
use Jb\Core\Response;
use Jb\Security\config\SecurityConfig;
use Jb\Security\detectors\BotDetector;
use Jb\Security\detectors\InjectionDetector;
use Jb\Security\detectors\LoginDetector;
use Jb\Security\detectors\MethodDetector;
use Jb\Security\detectors\NotFoundDetector;
use Jb\Security\detectors\PathDetector;
use Jb\Security\detectors\PayloadDetector;
use Jb\Security\detectors\RateLimitDetector;
use Jb\Security\detectors\SessionDetector;
use Jb\Security\services\CleanupService;
use Jb\Security\services\SecurityManager;
use Jb\Security\utils\SecurityRequest;

class SecurityMiddleware
{
    /** @var list<object> */
    private array $detectors;

    public function __construct(
        private readonly SecurityConfig $config,
        private readonly SecurityManager $manager,
        private readonly CleanupService $cleanup,
        MethodDetector $method,
        PathDetector $path,
        BotDetector $bot,
        PayloadDetector $payload,
        InjectionDetector $injection,
        RateLimitDetector $rateLimit,
        LoginDetector $login,
        NotFoundDetector $notFound,
        SessionDetector $session
    ) {
        $this->detectors = [$method, $path, $bot, $payload, $injection, $rateLimit, $login, $notFound, $session];
    }

    /**
     * Run the security detector pipeline before application routing.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->config->enabled() || $this->isExcluded($request)) {
            return $next($request);
        }

        $securityRequest = SecurityRequest::fromRequest($request);

        try {
            $this->cleanup->run();
            $preflight = $this->manager->preflight($securityRequest);

            if (($preflight['allow'] ?? false) === true) {
                return $next($request);
            }

            if (($preflight['blocked'] ?? false) === true) {
                throw new HttpException('Acceso bloqueado.', 403, 'SEC_BLOCKED', ['reason' => $securityRequest->ip]);
            }

            foreach ($this->detectors as $detector) {
                $result = $detector->analyze($securityRequest, $this->config);
                if (($result['blocked'] ?? false) !== true) {
                    continue;
                }

                $learning = (bool) $this->config->get('learning_mode', false);
                $this->manager->recordThreat($securityRequest, $result, !$learning);
                if (!$learning) {
                    throw new HttpException('Acceso bloqueado.', 403, 'SEC_BLOCKED', ['reason' => $securityRequest->ip]);
                }
            }
        } catch (HttpException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            if (!(bool) $this->config->get('fail_open', true)) {
                throw new HttpException('Modulo de seguridad no disponible.', 503);
            }

            return $next($request);
        }

        // --- Post-response phase: detectors that depend on the controller result ---
        try {
            $response = $next($request);
            $statusCode = $response->status();
        } catch (HttpException $exception) {
            $this->runPostResponseDetectors($securityRequest, $exception->statusCode());

            throw $exception;
        }

        $this->runPostResponseDetectors($securityRequest, $statusCode);

        return $response;
    }

    /**
     * Run post-response detectors (e.g. failed login, 404 scanning) and
     * persist threats. Never throws: this runs after the response is ready
     * and must not break the request/response cycle.
     */
    private function runPostResponseDetectors(SecurityRequest $securityRequest, int $statusCode): void
    {
        try {
            foreach ($this->detectors as $detector) {
                $result = $detector->analyzeResponse($securityRequest, $statusCode, $this->config);
                if (($result['blocked'] ?? false) !== true) {
                    continue;
                }

                $learning = (bool) $this->config->get('learning_mode', false);
                $this->manager->recordThreat($securityRequest, $result, !$learning);
            }
        } catch (\Throwable) {
            // Post-response analysis must never affect an already-generated response.
        }
    }

    private function isExcluded(Request $request): bool
    {
        $paths = (array) $this->config->get('excluded_paths', ['/health']);
        $extensions = (array) $this->config->get('excluded_extensions', ['.css', '.js', '.png', '.jpg', '.ico']);
        $path = $request->path();

        if (in_array($path, $paths, true)) {
            return true;
        }

        foreach ($extensions as $extension) {
            if (str_ends_with($path, (string) $extension)) {
                return true;
            }
        }

        return false;
    }
}
