<?php

declare(strict_types=1);

use Jb\Database\Blueprint;
use Jb\Database\Connection;
use Jb\Database\Migration;

return new class (Connection::getInstance()) extends Migration {
    /**
     * Create security module tables.
     */
    public function up(): void
    {
        $this->create('security_blocks', function (Blueprint $table): void {
            $table->id();
            $table->string('ip', 45);
            $table->string('reason', 150);
            $table->integer('score')->default(0);
            $table->timestamp('blocked_until');
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
        });

        $this->create('security_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('ip', 45);
            $table->integer('user_id')->nullable();
            $table->string('endpoint', 255);
            $table->string('http_method', 10);
            $table->string('reason', 150);
            $table->string('severity', 20);
            $table->integer('score')->default(0);
            $table->string('fingerprint', 128);
            $table->timestamp('created_at')->nullable();
        });

        $this->create('security_scores', function (Blueprint $table): void {
            $table->id();
            $table->string('score_key', 190);
            $table->string('fingerprint', 128);
            $table->string('ip', 45);
            $table->timestamp('window_start');
            $table->integer('attempts')->default(1);
            $table->timestamp('expires_at');
            $table->timestamp('updated_at')->nullable();
            $table->unique(['ip', 'window_start'], 'uq_ip_window');
            $table->unique(['fingerprint', 'window_start'], 'uq_fingerprint_window');
        });

        $this->create('security_whitelist', function (Blueprint $table): void {
            $table->id();
            $table->string('ip', 45)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        $this->create('security_blacklist', function (Blueprint $table): void {
            $table->id();
            $table->string('ip', 45)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        $this->create('security_audit', function (Blueprint $table): void {
            $table->id();
            $table->integer('user_id')->nullable();
            $table->string('action', 100);
            $table->string('target', 255);
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Drop security module tables.
     */
    public function down(): void
    {
        $this->drop('security_audit');
        $this->drop('security_blacklist');
        $this->drop('security_whitelist');
        $this->drop('security_scores');
        $this->drop('security_logs');
        $this->drop('security_blocks');
    }
};
