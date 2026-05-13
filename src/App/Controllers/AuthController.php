<?php

declare(strict_types=1);

namespace Jb\App\Controllers;

use Jb\Auth\AuthService;
use Jb\Core\HttpException;
use Jb\Core\Request;
use Jb\Core\Response;
use Jb\Core\Validator;

/**
 * Handle user authentication (login, refresh token).
 */
class AuthController
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    /**
     * User login with email and password.
     *
     * POST /api/auth/login
     * {
     *   "email": "user@example.com",
     *   "password": "secret123"
     * }
     */
    public function login(Request $request): Response
    {
        $validator = Validator::make($request->body(), [
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            throw new HttpException(
                'Validación fallida',
                422,
                'VALIDATION_ERROR',
                ['errors' => $validator->errors()]
            );
        }

        $email = $request->input('email');
        $password = $request->input('password');

        // TODO: Verify credentials against database
        // For now, we'll create a mock successful login
        $user = $this->mockUserAuthentication($email, $password);

        if (!$user) {
            throw new HttpException(
                'Credenciales inválidas',
                401,
                'INVALID_CREDENTIALS'
            );
        }

        $tokens = $this->authService->generateTokens([
            'sub' => $user['id'],
            'email' => $user['email'],
            'permissions' => $user['permissions'] ?? [],
        ]);

        return Response::success($tokens, 'Autenticación exitosa', [
            'user_id' => $user['id'],
            'email' => $user['email'],
        ]);
    }

    /**
     * Refresh an expired access token.
     *
     * POST /api/auth/refresh
     * {
     *   "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGci..."
     * }
     */
    public function refresh(Request $request): Response
    {
        $validator = Validator::make($request->body(), [
            'refresh_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new HttpException(
                'Validación fallida',
                422,
                'VALIDATION_ERROR',
                ['errors' => $validator->errors()]
            );
        }

        $refreshToken = $request->input('refresh_token');

        try {
            $newTokens = $this->authService->refreshAccessToken($refreshToken);

            return Response::success($newTokens, 'Token renovado');
        } catch (HttpException $e) {
            throw $e;
        }
    }

    /**
     * Revoke current access token and optional refresh token.
     *
     * POST /api/auth/logout
     */
    public function logout(Request $request): Response
    {
        $authorization = $request->header('authorization', '');
        $accessToken = AuthService::extractBearerToken($authorization);

        $this->authService->revokeToken($accessToken);
        $revoked = 1;

        $refreshToken = $request->input('refresh_token');
        if (is_string($refreshToken) && trim($refreshToken) !== '') {
            $this->authService->revokeToken($refreshToken);
            $revoked++;
        }

        return Response::success([
            'revoked_tokens' => $revoked,
        ], 'Sesión cerrada correctamente');
    }

    /**
     * Mock user authentication (replace with real DB query).
     *
     * @return array<string, mixed>|null
     */
    private function mockUserAuthentication(string $email, string $password): ?array
    {
        // This is a mock implementation
        // In production, query your users table with proper password hashing

        if ($email === 'admin@example.com' && $password === 'password123') {
            return [
                'id' => 1,
                'email' => 'admin@example.com',
                'permissions' => ['usuarios.read', 'usuarios.create', 'usuarios.update', 'usuarios.delete'],
            ];
        }

        if ($email === 'user@example.com' && $password === 'password456') {
            return [
                'id' => 2,
                'email' => 'user@example.com',
                'permissions' => ['usuarios.read'],
            ];
        }

        return null;
    }
}
