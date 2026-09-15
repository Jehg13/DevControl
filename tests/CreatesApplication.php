<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    private static bool $testingDatabasePrepared = false;

    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        if (! self::$testingDatabasePrepared) {
            $database = getenv('DB_DATABASE') ?: 'devcontrol_testing';
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '3306';
            $username = getenv('DB_USERNAME') ?: 'root';
            $password = getenv('DB_PASSWORD') ?: '';

            $pdo = new \PDO("mysql:host={$host};port={$port}", $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $quotedDatabase = str_replace('`', '``', $database);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$quotedDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            self::$testingDatabasePrepared = true;
        }

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        static $schemaPrepared = false;
        if (! $schemaPrepared) {
            $app->make(Kernel::class)->call('migrate:fresh', ['--seed' => true, '--force' => true]);
            $schemaPrepared = true;
        }

        return $app;
    }
}
