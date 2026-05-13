<?php

declare(strict_types=1);

namespace Jb\Core;

use Jb\Cache\CacheInterface;
use Jb\Cache\FileCache;
use Jb\Database\Connection;
use Jb\Logging\Logger;
use Jb\Logging\LoggerInterface;
use Jb\Mail\Mailer;
use Jb\RateLimit\RateLimiter;
use Jb\Security\SecurityMiddleware;
use Throwable;

class Application
{
    private Container $container;

    private Config $config;

    private Router $router;

    public function __construct(private readonly string $basePath)
    {
        $this->container = new Container();
        $this->config = new Config($basePath);
        $this->router = new Router();
    }

    /**
     * Bootstrap configuration and core services.
     */
    public function bootstrap(): self
    {
        $this->config->load();
        $this->container->instance(self::class, $this);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Config::class, $this->config);
        $this->container->instance(Router::class, $this->router);
        $this->container->instance(Connection::class, Connection::init($this->config));
        $this->registerSupportServices();

        return $this;
    }

    /**
     * Load route definitions from a PHP file.
     */
    public function routes(string $path): self
    {
        $router = $this->router;
        require $path;

        return $this;
    }

    /**
     * Handle one HTTP request and send the JSON response.
     */
    public function run(?Request $request = null): void
    {
        $request ??= Request::capture();
        $this->sendSecurityHeaders();
        $this->handleCors($request);

        if ($request->method() === 'OPTIONS') {
            http_response_code(204);
            return;
        }

        try {
            $request = $this->stripBaseRoute($request);
            $handler = fn (Request $request): Response => $this->router->dispatch($request, $this->container);
            $response = filter_var($this->config->get('security.enabled', true), FILTER_VALIDATE_BOOL)
                ? $this->container->get(SecurityMiddleware::class)->handle($request, $handler)
                : $handler($request);
            $response->send();
        } catch (HttpException $exception) {
            Response::error($exception->getMessage(), $exception->statusCode(), $exception->context())->send();
        } catch (Throwable $exception) {
            $payload = $this->config->isDebug() ? ['exception' => $exception->getMessage()] : [];
            Response::error('Error interno del servidor.', 500, $payload)->send();
        }
    }

    /**
     * Return the dependency injection container.
     */
    public function container(): Container
    {
        return $this->container;
    }

    /**
     * Return the loaded configuration repository.
     */
    public function config(): Config
    {
        return $this->config;
    }

    private function sendSecurityHeaders(): void
    {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }

    private function handleCors(Request $request): void
    {
        $origin = $request->header('origin', '');
        $allowed = (string) $this->config->get('app.cors.allowed_origins', '*');

        if ($allowed === '*') {
            header('Access-Control-Allow-Origin: *');
        } elseif ($origin !== '' && in_array($origin, array_map('trim', explode(',', $allowed)), true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    }

    private function stripBaseRoute(Request $request): Request
    {
        $baseRoute = '/' . trim((string) $this->config->get('app.base_route', ''), '/');
        if ($baseRoute === '/' || !str_starts_with($request->path(), $baseRoute)) {
            return $request;
        }

        $path = substr($request->path(), strlen($baseRoute)) ?: '/';

        return $request->withPath($path);
    }

    private function registerSupportServices(): void
    {
        $this->container->bind(LoggerInterface::class, fn (): Logger => new Logger($this->path(
            (string) $this->config->get('logging.path', 'storage/logs/app.log')
        )));
        $this->container->bind(Logger::class, fn (Container $container): LoggerInterface => $container->get(LoggerInterface::class));
        $this->container->bind(CacheInterface::class, fn (): FileCache => new FileCache($this->path(
            (string) $this->config->get('cache.path', 'storage/cache')
        )));
        $this->container->bind(FileCache::class, fn (Container $container): CacheInterface => $container->get(CacheInterface::class));
        $this->container->bind(RateLimiter::class, fn (): RateLimiter => new RateLimiter(
            $this->path((string) $this->config->get('rate_limit.path', 'storage/rate_limit')),
            (int) $this->config->get('rate_limit.max_attempts', 120),
            (int) $this->config->get('rate_limit.window_seconds', 60)
        ));
        $this->container->bind(Mailer::class, fn (): Mailer => new Mailer(
            (string) $this->config->get('mail.from_address', 'noreply@example.com'),
            (string) $this->config->get('mail.from_name', 'JB API')
        ));
    }

    private function path(string $path): string
    {
        if (preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1 || str_starts_with($path, '/')) {
            return $path;
        }

        return $this->basePath . DIRECTORY_SEPARATOR . $path;
    }
}
