<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        putenv('APP_ENV=testing');
        putenv('DB_CONNECTION=mysql');
        putenv('DB_DATABASE=devcontrol_testing');

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql.database', 'devcontrol_testing');
        DB::purge('mysql');

        return $app;
    }
}
