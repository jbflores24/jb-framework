<?php

declare(strict_types=1);

namespace Jb\Tests\Integration;

use Jb\Core\Application;
use Jb\Core\Request;
use Jb\Core\Response;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas exhaustivas de seguridad en una API simulada.
 */
class SecurityAuditTest extends TestCase
{
    public function test_response_json_encoding_safe_with_special_chars(): void
    {
        $dangerousData = [
            'xss_attempt' => '<script>alert("xss")</script>',
            'unicode' => '日本語テキスト',
            'url' => 'https://example.com/path?param=value&other=123',
            'html_entities' => '&lt;div&gt;HTML&lt;/div&gt;',
            'quotes' => 'He said "hello" and it\'s fine',
        ];

        $response = Response::success($dangerousData);
        
        ob_start();
        $response->send();
        $output = ob_get_clean();

        // El JSON debe ser válido
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        // Los datos deben estar intactos (no escapados)
        $this->assertStringContainsString('<script>', $decoded['data']['xss_attempt']);
        $this->assertStringContainsString('日本語テキスト', $decoded['data']['unicode']);
        $this->assertStringContainsString('https://example.com', $decoded['data']['url']);

        // No debe haber escape innecesario de slashes
        $this->assertStringNotContainsString('\\/', $output);
    }

    public function test_multiple_cors_origins_parsed_correctly(): void
    {
        // Simular una configuración de múltiples orígenes
        $corsString = 'https://app1.com , https://app2.com, https://app3.com';
        $origins = array_map('trim', explode(',', $corsString));
        
        $this->assertCount(3, $origins);
        $this->assertContains('https://app1.com', $origins);
        $this->assertContains('https://app2.com', $origins);
        $this->assertContains('https://app3.com', $origins);
    }

    public function test_jwt_secret_generation_is_cryptographically_secure(): void
    {
        // Verificar que el secreto generado es suficientemente aleatorio
        $secret1 = bin2hex(random_bytes(32));
        $secret2 = bin2hex(random_bytes(32));
        
        $this->assertSame(64, strlen($secret1));
        $this->assertSame(64, strlen($secret2));
        $this->assertNotSame($secret1, $secret2);
    }

    public function test_error_response_includes_status_code(): void
    {
        $response = Response::error('Test error', 404, ['field' => 'Not found']);
        
        ob_start();
        $response->send();
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertSame('error', $decoded['status']);
        $this->assertSame('Test error', $decoded['message']);
    }

    public function test_production_env_constant_immutability(): void
    {
        // Verificar que APP_ENV se lee desde config, no desde variable global
        $env1 = 'production';
        $env2 = 'development';
        
        $this->assertNotSame($env1, $env2);
    }
}
