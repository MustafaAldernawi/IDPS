<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        if ($app['config']->get('database.connections.mysql.database') !== 'idps_test') {
            throw new \RuntimeException('Tests may only use dedicated idps_test database');
        }

        return $app;
    }
}
