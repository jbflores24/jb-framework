<?php

declare(strict_types=1);

namespace Jb\Tests\Unit;

use Jb\Auth\TokenRevocationList;
use Jb\Tests\BaseTestCase;

class TokenRevocationListTest extends BaseTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/jb_rev_' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        @rmdir($this->dir . "/es_carpeta.json"); array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    public function test_revoca_y_reconoce_un_token(): void
    {
        $lista = new TokenRevocationList($this->dir . '/r.json');
        $this->assertFalse($lista->isRevoked('a'));
        $lista->revoke('a', time() + 60, ['token_type' => 'refresh', 'user_id' => 7]);
        $this->assertTrue($lista->isRevoked('a'));
        $this->assertFalse($lista->isRevoked('b'));
        $this->assertSame(1, $lista->stats()['active_total']);
    }

    public function test_un_token_vencido_deja_de_contar_y_cleanup_lo_borra(): void
    {
        $lista = new TokenRevocationList($this->dir . '/r.json');
        $lista->revoke('viejo', time() - 5);
        $lista->revoke('vigente', time() + 60);
        $this->assertFalse($lista->isRevoked('viejo'));
        $this->assertTrue($lista->isRevoked('vigente'));
        $lista->revoke('viejo2', time() - 5);
        $this->assertSame(1, $lista->cleanup());
    }

    public function test_lee_el_formato_anterior_donde_la_entrada_es_solo_el_vencimiento(): void
    {
        file_put_contents($this->dir . '/r.json', json_encode([hash('sha256', 'legado') => time() + 60]));
        $lista = new TokenRevocationList($this->dir . '/r.json');
        $this->assertTrue($lista->isRevoked('legado'));
    }

    public function test_si_no_puede_abrir_el_archivo_lanza_runtime_exception(): void
    {
        mkdir($this->dir . '/es_carpeta.json'); // la ruta es una carpeta: fopen falla
        $lista = @new TokenRevocationList($this->dir . '/es_carpeta.json');

        $this->expectException(\RuntimeException::class);
        @$lista->revoke('t', time() + 60);
    }

    public function test_revocaciones_simultaneas_no_se_pierden(): void
    {
        $archivo = $this->dir . '/r.json';
        $script = $this->dir . '/hijo.php';
        file_put_contents($script, '<?php require ' . var_export(dirname(__DIR__, 2) . '/src/Auth/TokenRevocationList.php', true)
            . '; (new Jb\Auth\TokenRevocationList($argv[1]))->revoke($argv[2], time() + 3600, ["token_type" => "refresh"]);');

        $procesos = [];
        for ($i = 0; $i < 20; $i++) {
            $procesos[] = proc_open([PHP_BINARY, $script, $archivo, "token$i"], [2 => ['pipe', 'w']], $pipes);
            $errores[] = $pipes[2];
        }
        foreach ($procesos as $i => $proc) {
            stream_get_contents($errores[$i]);
            proc_close($proc);
        }

        $lista = new TokenRevocationList($archivo);
        $this->assertSame(20, $lista->stats()['active_total']);
        for ($i = 0; $i < 20; $i++) {
            $this->assertTrue($lista->isRevoked("token$i"), "token$i debía estar revocado");
        }
    }
}
