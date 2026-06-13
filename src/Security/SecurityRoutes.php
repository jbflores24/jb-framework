<?php

declare(strict_types=1);

namespace Jb\Security;

use Jb\Core\Router;
use Jb\Security\Controllers\SecurityAdminController;

class SecurityRoutes
{
    /**
     * Register security administration JSON endpoints.
     */
    public static function register(Router $router, array $middleware = []): void
    {
        $controller = SecurityAdminController::class;
        $router->get('/security/dashboard', [$controller, 'dashboard'], $middleware);
        $router->get('/security/blocks', [$controller, 'blocks'], $middleware);
        $router->post('/security/blocks/block', [$controller, 'block'], $middleware);
        $router->post('/security/blocks/unblock', [$controller, 'unblock'], $middleware);
        $router->get('/security/logs', [$controller, 'logs'], $middleware);
        $router->get('/security/whitelist', [$controller, 'whitelist'], $middleware);
        $router->post('/security/whitelist/add', [$controller, 'addWhitelist'], $middleware);
        $router->post('/security/whitelist/remove', [$controller, 'removeWhitelist'], $middleware);
        $router->get('/security/blacklist', [$controller, 'blacklist'], $middleware);
        $router->post('/security/blacklist/add', [$controller, 'addBlacklist'], $middleware);
        $router->post('/security/blacklist/remove', [$controller, 'removeBlacklist'], $middleware);
        $router->get('/security/export/csv', [$controller, 'exportCsv'], $middleware);
    }
}
