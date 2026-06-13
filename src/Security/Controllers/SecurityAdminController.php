<?php

declare(strict_types=1);

namespace Jb\Security\Controllers;

use Jb\Core\HttpException;
use Jb\Core\Request;
use Jb\Core\Response;
use Jb\Security\services\SecurityManager;

class SecurityAdminController
{
    public function __construct(private readonly SecurityManager $security)
    {
    }

    /**
     * GET /api/security/dashboard
     */
    public function dashboard(Request $request): Response
    {
        return Response::success($this->security->stats());
    }

    /**
     * GET /api/security/blocks
     */
    public function blocks(Request $request): Response
    {
        return Response::success($this->security->blocks()->list());
    }

    /**
     * POST /api/security/blocks/block
     */
    public function block(Request $request): Response
    {
        $ip = (string) $request->input('ip', '');
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new HttpException('IP invalida.', 422);
        }

        $this->security->blockIp($ip, (string) $request->input('reason', 'Bloqueo manual'), $this->adminId($request));

        return Response::success(null, 'IP bloqueada.');
    }

    /**
     * POST /api/security/blocks/unblock
     */
    public function unblock(Request $request): Response
    {
        $ip = (string) $request->input('ip', '');
        $count = $this->security->unblockIp($ip, $this->adminId($request));

        return Response::success(['updated' => $count], 'IP desbloqueada.');
    }

    /**
     * GET /api/security/logs
     */
    public function logs(Request $request): Response
    {
        return Response::success($this->security->logs()->list());
    }

    /**
     * GET /api/security/whitelist
     */
    public function whitelist(Request $request): Response
    {
        return Response::success($this->security->whitelist()->list());
    }

    /**
     * POST /api/security/whitelist/add
     */
    public function addWhitelist(Request $request): Response
    {
        $this->security->whitelist()->add($this->ip($request), (string) $request->input('description', ''));

        return Response::success(null, 'IP agregada a whitelist.');
    }

    /**
     * POST /api/security/whitelist/remove
     */
    public function removeWhitelist(Request $request): Response
    {
        $count = $this->security->whitelist()->remove($this->ip($request));

        return Response::success(['updated' => $count], 'IP eliminada de whitelist.');
    }

    /**
     * GET /api/security/blacklist
     */
    public function blacklist(Request $request): Response
    {
        return Response::success($this->security->blacklist()->list());
    }

    /**
     * POST /api/security/blacklist/add
     */
    public function addBlacklist(Request $request): Response
    {
        $this->security->blacklist()->add($this->ip($request), (string) $request->input('description', ''));

        return Response::success(null, 'IP agregada a blacklist.');
    }

    /**
     * POST /api/security/blacklist/remove
     */
    public function removeBlacklist(Request $request): Response
    {
        $count = $this->security->blacklist()->remove($this->ip($request));

        return Response::success(['updated' => $count], 'IP eliminada de blacklist.');
    }

    /**
     * GET /api/security/export/csv
     */
    public function exportCsv(Request $request): Response
    {
        $rows = $this->security->logs()->list(500);
        $csv = "created_at,ip,user_id,endpoint,http_method,reason,severity,score\n";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn (mixed $value): string => '"' . str_replace('"', '""', (string) $value) . '"', [
                $row['created_at'] ?? '',
                $row['ip'] ?? '',
                $row['user_id'] ?? '',
                $row['endpoint'] ?? '',
                $row['http_method'] ?? '',
                $row['reason'] ?? '',
                $row['severity'] ?? '',
                $row['score'] ?? '',
            ])) . "\n";
        }

        return Response::success(['filename' => 'security_logs.csv', 'csv' => $csv]);
    }

    private function adminId(Request $request): ?int
    {
        $auth = $request->attribute('auth', []);

        return is_array($auth) && isset($auth['id']) ? (int) $auth['id'] : null;
    }

    private function ip(Request $request): string
    {
        $ip = (string) $request->input('ip', '');
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new HttpException('IP invalida.', 422);
        }

        return $ip;
    }
}
