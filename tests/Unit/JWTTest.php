<?php

declare(strict_types=1);

namespace Jb\Tests\Unit;

use Jb\Auth\JWT;
use Jb\Core\HttpException;
use Jb\Tests\BaseTestCase;

final class JWTTest extends BaseTestCase
{
    public function testEncodeAndDecodeToken(): void
    {
        $jwt = new JWT('clave-secreta');
        $token = $jwt->encode(['sub' => 7, 'permissions' => ['usuarios.read']], 3600);

        $claims = $jwt->decode($token);

        $this->assertSame(7, $claims['sub']);
        $this->assertSame(['usuarios.read'], $claims['permissions']);
        $this->assertArrayHasKey('iat', $claims);
        $this->assertArrayHasKey('exp', $claims);
    }

    public function testDecodeFailsWithInvalidSignature(): void
    {
        $jwt = new JWT('clave-secreta');
        $token = $jwt->encode(['sub' => 1], 3600);
        $invalid = $token . 'x';

        $this->expectException(HttpException::class);

        try {
            $jwt->decode($invalid);
        } catch (HttpException $exception) {
            $this->assertSame(401, $exception->statusCode());
            throw $exception;
        }
    }

    public function testDecodeFailsWhenTokenIsExpired(): void
    {
        $jwt = new JWT('clave-secreta');
        $token = $jwt->encode(['sub' => 1], -1);

        $this->expectException(HttpException::class);

        try {
            $jwt->decode($token);
        } catch (HttpException $exception) {
            $this->assertSame(401, $exception->statusCode());
            throw $exception;
        }
    }
}
