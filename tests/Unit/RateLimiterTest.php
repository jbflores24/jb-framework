<?php

declare(strict_types=1);

namespace Jb\Tests\Unit;

use Jb\RateLimit\RateLimiter;
use Jb\Tests\BaseTestCase;

class RateLimiterTest extends BaseTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/jb_rl_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new RateLimiter($this->dir))->flush();
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    public function test_bloquea_al_pasar_el_limite_y_cada_identificador_va_aparte(): void
    {
        $rl = new RateLimiter($this->dir, 3, 60);
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($rl->check('ip_1')['allowed']);
        }
        $this->assertFalse($rl->check('ip_1')['allowed']);
        $this->assertTrue($rl->check('ip_2')['allowed']);
        $this->assertSame(3, $rl->status('ip_1')['count']);
    }

    public function test_la_ventana_es_deslizante_y_no_depende_del_minuto_del_calendario(): void
    {
        $rl = new RateLimiter($this->dir, 2, 60);
        $rl->check('x');
        $file = glob($this->dir . '/rate_limit_*.json')[0];

        file_put_contents($file, json_encode([time() - 59, time() - 59]));
        $this->assertFalse($rl->check('x')['allowed']);

        file_put_contents($file, json_encode([time() - 61, time() - 61]));
        $this->assertTrue($rl->check('x')['allowed']);
    }

    public function test_reset_y_estado_danado(): void
    {
        $rl = new RateLimiter($this->dir, 1, 60);
        $rl->check('y');
        $this->assertFalse($rl->check('y')['allowed']);
        $rl->reset('y');
        $this->assertTrue($rl->check('y')['allowed']);

        file_put_contents(glob($this->dir . '/rate_limit_*.json')[0], '{no es json');
        $this->assertTrue($rl->check('y')['allowed']);
    }

    public function test_peticiones_simultaneas_no_rebasan_el_limite(): void
    {
        mkdir($this->dir, 0755, true);
        $script = $this->dir . '/hijo.php';
        file_put_contents($script, '<?php require ' . var_export(dirname(__DIR__, 2) . '/src/RateLimit/RateLimiter.php', true)
            . '; echo (new Jb\RateLimit\RateLimiter($argv[1], 5, 60))->check("c")["allowed"] ? "1" : "0";');

        $procesos = [];
        for ($i = 0; $i < 12; $i++) {
            $proc = proc_open([PHP_BINARY, $script, $this->dir], [1 => ['pipe', 'w']], $pipes);
            $procesos[] = [$proc, $pipes[1]];
        }

        $pasaron = 0;
        foreach ($procesos as [$proc, $salida]) {
            $pasaron += (int) stream_get_contents($salida);
            proc_close($proc);
        }

        $this->assertSame(5, $pasaron);
    }
}
