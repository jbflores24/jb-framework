<?php

declare(strict_types=1);

namespace Jb\Auth;

use Jb\Core\HttpException;

class JWT
{
    public function __construct(private readonly string $secret)
    {
    }

    /**
     * Encode a payload as a HMAC-SHA256 JWT.
     *
     * @param array<string, mixed> $payload
     */
    public function encode(array $payload, int $ttl): string
    {
        $now = time();
        $payload = array_merge(['iat' => $now, 'exp' => $now + $ttl], $payload);
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];
        $segments[] = $this->sign(implode('.', $segments));

        return implode('.', $segments);
    }

    /**
     * Decode and verify a JWT payload.
     *
     * @return array<string, mixed>
     */
    public function decode(string $token): array
    {
        $segments = explode('.', $token);
        if (count($segments) !== 3) {
            throw new HttpException('Token inválido.', 401, 'INVALID_TOKEN');
        }

        [$header, $payload, $signature] = $segments;
        if (!hash_equals($this->sign($header . '.' . $payload), $signature)) {
            throw new HttpException('Firma de token inválida.', 401, 'INVALID_SIGNATURE');
        }

        $data = json_decode($this->base64UrlDecode($payload), true);
        if (!is_array($data)) {
            throw new HttpException('Payload de token inválido.', 401, 'INVALID_PAYLOAD');
        }

        if (isset($data['exp']) && time() >= (int) $data['exp']) {
            throw new HttpException('Token expirado.', 401, 'TOKEN_EXPIRED');
        }

        return $data;
    }

    private function sign(string $value): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $value, $this->secret, true));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($value, '-_', '+/'), true) ?: '';
    }
}
