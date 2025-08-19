<?php

namespace SulimanBenhalim\LaravelSuperJson;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;
use SulimanBenhalim\LaravelSuperJson\Exceptions\SuperJsonException;

class SuperJsonServiceProvider extends ServiceProvider
{
    /**
     * Register SuperJSON services with configuration validation
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__.'/../config/superjson.php', 'superjson'
        );

        // Register the main SuperJson service
        $this->app->singleton('superjson', function ($app) {
            // Validate configuration before creating instance
            $this->validateConfiguration();

            return new SuperJson(
                config('superjson.transformers', []),
                config('superjson.options', [])
            );
        });

        // Register alias for facade
        $this->app->alias('superjson', SuperJson::class);
    }

    /**
     * Bootstrap SuperJSON services including macros, Blade directives, and middleware
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/superjson.php' => config_path('superjson.php'),
            ], 'superjson-config');
        }

        $this->registerResponseMacro();
        $this->registerRequestMacro();
        $this->registerBladeDirective();
        $this->registerMiddleware();
    }

    /**
     * Register response()->superjson() macro for serializing data with SuperJSON format
     */
    protected function registerResponseMacro(): void
    {
        Response::macro('superjson', function ($data, $status = 200, array $headers = []) {
            $serialized = app('superjson')->serialize($data);

            return Response::json(
                $serialized,
                $status,
                array_merge($headers, ['Content-Type' => 'application/superjson'])
            );
        });

        // Shorter alias
        Response::macro('sjson', function ($data, $status = 200, array $headers = []) {
            return Response::superjson($data, $status, $headers);
        });
    }

    /**
     * Register request()->superjson() macro for securely accessing deserialized SuperJSON data
     */
    protected function registerRequestMacro(): void
    {
        Request::macro('superjson', function ($key = null) {
            // First try to get data from middleware (secure method)
            $data = $this->attributes->get('superjson_data');

            if ($data !== null) {
                return $key ? data_get($data, $key) : $data;
            }

            // Fallback: parse directly from content (less secure, for compatibility)
            $content = $this->getContent();

            if (empty($content)) {
                return null;
            }

            try {
                $deserialized = app('superjson')->deserialize($content);

                return $key ? data_get($deserialized, $key) : $deserialized;
            } catch (\Exception $e) {
                // Return null on deserialization failure
                return null;
            }
        });

        // Alias
        Request::macro('sjson', function ($key = null) {
            return $this->superjson($key);
        });
    }

    /**
     * Register @superjson Blade directive for HTML-safe SuperJSON embedding
     */
    protected function registerBladeDirective(): void
    {
        Blade::directive('superjson', function ($expression) {
            return "<?php echo app('superjson')->toHtml($expression); ?>";
        });
    }

    /**
     * Register 'superjson' middleware alias for automatic SuperJSON request/response handling
     */
    protected function registerMiddleware(): void
    {
        $this->app['router']->aliasMiddleware(
            'superjson',
            \SulimanBenhalim\LaravelSuperJson\Http\Middleware\HandleSuperJsonRequests::class
        );
    }

    /**
     * Validate SuperJSON configuration for security compliance and correctness
     *
     * @throws SuperJsonException If configuration contains invalid or insecure settings
     */
    protected function validateConfiguration(): void
    {
        $config = config('superjson', []);

        // Validate transformers
        if (isset($config['transformers'])) {
            if (! is_array($config['transformers'])) {
                throw SuperJsonException::securityViolation('Configuration error: transformers must be an array');
            }

            foreach ($config['transformers'] as $transformer) {
                if (! is_string($transformer) || ! class_exists($transformer)) {
                    throw SuperJsonException::securityViolation("Configuration error: Invalid transformer class '$transformer'");
                }
            }
        }

        // Validate security settings
        if (isset($config['security'])) {
            $security = $config['security'];

            if (isset($security['max_depth']) && (! is_int($security['max_depth']) || $security['max_depth'] < 1)) {
                throw SuperJsonException::securityViolation('Configuration error: security.max_depth must be a positive integer');
            }

            if (isset($security['max_array_size']) && (! is_int($security['max_array_size']) || $security['max_array_size'] < 1)) {
                throw SuperJsonException::securityViolation('Configuration error: security.max_array_size must be a positive integer');
            }

            if (isset($security['allow_class_restoration']) && ! is_bool($security['allow_class_restoration'])) {
                throw SuperJsonException::securityViolation('Configuration error: security.allow_class_restoration must be a boolean');
            }

            if (isset($security['allowed_classes']) && ! is_array($security['allowed_classes'])) {
                throw SuperJsonException::securityViolation('Configuration error: security.allowed_classes must be an array');
            }
        }

        // Validate options
        if (isset($config['options'])) {
            $options = $config['options'];

            if (isset($options['max_depth']) && (! is_int($options['max_depth']) || $options['max_depth'] < 1)) {
                throw SuperJsonException::securityViolation('Configuration error: options.max_depth must be a positive integer');
            }
        }
    }
}
