<?php

declare(strict_types=1);

namespace Jb\Tests\Integration;

use Jb\Database\Connection;
use Jb\Database\Migrator;
use Jb\Tests\BaseTestCase;

final class MigrationTest extends BaseTestCase
{
    public function testRunStatusAndRollbackWithSqlite(): void
    {
        $basePath = $this->createTempPath('migrator-');
        $migrationsPath = $basePath . DIRECTORY_SEPARATOR . 'migrations';

        $this->writeFile(
            $migrationsPath . DIRECTORY_SEPARATOR . '2026_05_12_143000_create_posts.php',
            <<<'PHP'
<?php

declare(strict_types=1);

use Jb\Database\Blueprint;
use Jb\Database\Connection;
use Jb\Database\Migration;

return new class(Connection::getInstance()) extends Migration {
    public function up(): void
    {
        $this->create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('titulo', 150);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('posts');
    }
};
PHP
        );

        $config = $this->makeConfig($basePath, [], [
            'driver' => 'sqlite',
            'path' => ':memory:',
        ]);

        $connection = Connection::init($config);
        $migrator = new Migrator($connection, $migrationsPath);

        $executed = $migrator->run();
        $statusAfterRun = $migrator->status();
        $rolledBack = $migrator->rollback();

        $this->assertSame(['2026_05_12_143000_create_posts'], $executed);
        $this->assertTrue($statusAfterRun[0]['ran']);
        $this->assertSame(['2026_05_12_143000_create_posts'], $rolledBack);
    }
}
