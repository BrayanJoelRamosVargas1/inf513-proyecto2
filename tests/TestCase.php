<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if (config('database.connections.'.config('database.default').'.database') !== 'ficct_test') {
            throw new \RuntimeException('Las pruebas requieren una base separada llamada ficct_test. Use python scripts/test.py');
        }

        return $app;
    }
}
