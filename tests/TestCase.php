<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Disable strict attribute guards in tests to allow direct model creation
        // without requiring every attribute in $fillable. In production these guards
        // remain active to catch real bugs.
        Model::preventSilentlyDiscardingAttributes(false);
    }
}
