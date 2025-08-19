<?php

namespace SulimanBenhalim\LaravelSuperJson\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SulimanBenhalim\LaravelSuperJson\SuperJsonServiceProvider;

/**
 * Base test case for SuperJSON package tests
 * Sets up Laravel environment with SuperJSON service provider
 */
abstract class TestCase extends Orchestra
{
    /**
     * Set up test environment before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Additional setup if needed
    }

    /**
     * Register SuperJSON service provider for testing
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getPackageProviders($app): array
    {
        return [
            SuperJsonServiceProvider::class,
        ];
    }

    /**
     * Configure test environment with in-memory database and SuperJSON settings
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Configure test environment with in-memory SQLite database
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // SuperJSON configuration for testing - enable error throwing
        $app['config']->set('superjson.options.throw_on_error', true);
    }
}
