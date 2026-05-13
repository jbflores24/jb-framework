<?php

declare(strict_types=1);

namespace App\Controllers;

use Jb\Core\Request;
use Jb\Core\Response;

class PingController
{
    /**
     * List resources.
     */
    public function index(Request $request): Response
    {
        return Response::success([]);
    }
}
