<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Guards (notably Sanctum's request guard) cache the resolved user for the life of the
     * test process. Real requests start fresh, so switching users here resets them too.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        return parent::actingAs($user, $guard);
    }
}
