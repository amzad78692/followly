<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests must never reach YouTube or Apify; fake every request.
        Http::preventStrayRequests();

        config(['platforms.retry_backoff' => [0]]);
    }
}
