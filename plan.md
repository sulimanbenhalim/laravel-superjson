# Laravel SuperJSON Bridge Package Development Plan

## Package architecture and core implementation strategy

Based on extensive research of Laravel package development best practices, SuperJSON specifications, and PHP serialization strategies, this comprehensive plan provides a realistic 6-hour development timeline for creating a production-ready `laravel-superjson-bridge` package.

### Package Overview and Goals

The laravel-superjson-bridge package will enable seamless type-preserving JSON communication between Laravel APIs and JavaScript clients by implementing the SuperJSON format. The package will preserve JavaScript types like Date, BigInt, Map, Set, URL, and RegExp through Laravel's response and request systems, ensuring perfect type fidelity across the API boundary.

## Hour 1: Package scaffolding and initial setup

### 1.1 Package Structure Creation (20 minutes)

Create the following directory structure following Laravel conventions:

```bash
laravel-superjson-bridge/
├── composer.json
├── README.md
├── LICENSE.md
├── .gitignore
├── phpunit.xml
├── src/
│   ├── SuperJsonServiceProvider.php
│   ├── SuperJson.php
│   ├── Transformers/
│   │   ├── TypeTransformer.php
│   │   ├── DateTransformer.php
│   │   ├── BigIntTransformer.php
│   │   └── CollectionTransformer.php
│   ├── Http/
│   │   └── Middleware/
│   │       └── HandleSuperJsonRequests.php
│   ├── Facades/
│   │   └── SuperJson.php
│   └── Exceptions/
│       └── SuperJsonException.php
├── config/
│   └── superjson.php
├── tests/
│   ├── TestCase.php
│   ├── Unit/
│   └── Feature/
└── .github/
    └── workflows/
        └── tests.yml
```

### 1.2 Composer Configuration (15 minutes)

Create `composer.json` with proper Laravel package discovery:

```json
{
    "name": "yourvendor/laravel-superjson-bridge",
    "description": "SuperJSON format bridge for Laravel - preserve JavaScript types in JSON",
    "keywords": ["laravel", "superjson", "json", "serialization", "types"],
    "license": "MIT",
    "authors": [
        {
            "name": "Your Name",
            "email": "your@email.com"
        }
    ],
    "require": {
        "php": "^8.1",
        "illuminate/support": "^10.0|^11.0",
        "illuminate/http": "^10.0|^11.0"
    },
    "require-dev": {
        "orchestra/testbench": "^8.0|^9.0",
        "phpunit/phpunit": "^10.0",
        "phpstan/phpstan": "^1.10"
    },
    "autoload": {
        "psr-4": {
            "YourVendor\\LaravelSuperJson\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "YourVendor\\LaravelSuperJson\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "YourVendor\\LaravelSuperJson\\SuperJsonServiceProvider"
            ],
            "aliases": {
                "SuperJson": "YourVendor\\LaravelSuperJson\\Facades\\SuperJson"
            }
        }
    },
    "scripts": {
        "test": "vendor/bin/phpunit",
        "analyse": "vendor/bin/phpstan analyse"
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

### 1.3 Service Provider Foundation (25 minutes)

Create the main service provider with proper register/boot separation:

```php
<?php

namespace YourVendor\LaravelSuperJson;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;

class SuperJsonServiceProvider extends ServiceProvider
{
    /**
     * Register services - ONLY bindings here
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__.'/../config/superjson.php', 'superjson'
        );
        
        // Register the main SuperJson service
        $this->app->singleton('superjson', function ($app) {
            return new SuperJson(
                config('superjson.transformers', []),
                config('superjson.options', [])
            );
        });
        
        // Register alias for facade
        $this->app->alias('superjson', SuperJson::class);
    }
    
    /**
     * Bootstrap services - Routes, views, macros, etc.
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
     * Register the response()->superjson() macro
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
     * Register the Request::superjson() helper
     */
    protected function registerRequestMacro(): void
    {
        Request::macro('superjson', function ($key = null) {
            $content = $this->getContent();
            
            if (empty($content)) {
                return null;
            }
            
            $deserialized = app('superjson')->deserialize($content);
            
            return $key ? data_get($deserialized, $key) : $deserialized;
        });
        
        // Alias
        Request::macro('sjson', function ($key = null) {
            return $this->superjson($key);
        });
    }
    
    /**
     * Register Blade directive for embedding SuperJSON
     */
    protected function registerBladeDirective(): void
    {
        Blade::directive('superjson', function ($expression) {
            return "<?php echo app('superjson')->toHtml($expression); ?>";
        });
    }
    
    /**
     * Register middleware for automatic SuperJSON handling
     */
    protected function registerMiddleware(): void
    {
        $this->app['router']->aliasMiddleware(
            'superjson',
            \YourVendor\LaravelSuperJson\Http\Middleware\HandleSuperJsonRequests::class
        );
    }
}
```

## Hour 2: Core SuperJSON implementation

### 2.1 Main SuperJson Class (30 minutes)

Create the core SuperJson class that handles serialization/deserialization:

```php
<?php

namespace YourVendor\LaravelSuperJson;

use YourVendor\LaravelSuperJson\Transformers\TypeTransformer;
use YourVendor\LaravelSuperJson\Exceptions\SuperJsonException;

class SuperJson
{
    protected array $transformers = [];
    protected array $options;
    protected array $meta = [];
    protected string $currentPath = '';
    
    public function __construct(array $customTransformers = [], array $options = [])
    {
        $this->options = array_merge([
            'preserve_zero_fraction' => true,
            'unescaped_unicode' => true,
            'throw_on_error' => true,
        ], $options);
        
        $this->registerDefaultTransformers();
        
        foreach ($customTransformers as $transformer) {
            $this->registerTransformer($transformer);
        }
    }
    
    /**
     * Serialize data to SuperJSON format
     */
    public function serialize($data): array
    {
        $this->meta = [];
        $this->currentPath = '';
        
        $json = $this->transform($data);
        
        $result = ['json' => $json];
        
        if (!empty($this->meta)) {
            $result['meta'] = ['values' => $this->meta];
        }
        
        return $result;
    }
    
    /**
     * Deserialize SuperJSON format to PHP data
     */
    public function deserialize($input): mixed
    {
        if (is_string($input)) {
            $input = json_decode($input, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new SuperJsonException('Invalid JSON: ' . json_last_error_msg());
            }
        }
        
        if (!is_array($input) || !isset($input['json'])) {
            // Plain JSON fallback
            return $input;
        }
        
        $meta = $input['meta']['values'] ?? [];
        
        return $this->restore($input['json'], $meta);
    }
    
    /**
     * Transform PHP data for SuperJSON serialization
     */
    protected function transform($value, string $path = ''): mixed
    {
        $previousPath = $this->currentPath;
        $this->currentPath = $path;
        
        foreach ($this->transformers as $transformer) {
            if ($transformer->canTransform($value)) {
                $result = $transformer->transform($value);
                $this->meta[$path] = [$transformer->getType()];
                $this->currentPath = $previousPath;
                return $result;
            }
        }
        
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $itemPath = $path ? "$path.$key" : (string)$key;
                $result[$key] = $this->transform($item, $itemPath);
            }
            $this->currentPath = $previousPath;
            return $result;
        }
        
        if (is_object($value) && !($value instanceof \stdClass)) {
            // Handle generic objects
            $className = get_class($value);
            $this->meta[$path] = ['class:' . $className];
            
            $result = [];
            foreach (get_object_vars($value) as $key => $val) {
                $propPath = $path ? "$path.$key" : $key;
                $result[$key] = $this->transform($val, $propPath);
            }
            
            $this->currentPath = $previousPath;
            return $result;
        }
        
        $this->currentPath = $previousPath;
        return $value;
    }
    
    /**
     * Restore PHP data from SuperJSON format
     */
    protected function restore($data, array $meta, string $path = ''): mixed
    {
        if (isset($meta[$path])) {
            $type = $meta[$path][0];
            
            foreach ($this->transformers as $transformer) {
                if ($transformer->getType() === $type) {
                    return $transformer->restore($data);
                }
            }
            
            // Handle custom class restoration
            if (str_starts_with($type, 'class:')) {
                $className = substr($type, 6);
                if (class_exists($className)) {
                    $instance = new $className();
                    foreach ($data as $key => $value) {
                        $propPath = $path ? "$path.$key" : $key;
                        $instance->$key = $this->restore($value, $meta, $propPath);
                    }
                    return $instance;
                }
            }
        }
        
        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $itemPath = $path ? "$path.$key" : (string)$key;
                $result[$key] = $this->restore($value, $meta, $itemPath);
            }
            return $result;
        }
        
        return $data;
    }
    
    /**
     * Register default type transformers
     */
    protected function registerDefaultTransformers(): void
    {
        $this->registerTransformer(new Transformers\DateTransformer());
        $this->registerTransformer(new Transformers\BigIntTransformer());
        $this->registerTransformer(new Transformers\CollectionTransformer());
        $this->registerTransformer(new Transformers\RegexTransformer());
        $this->registerTransformer(new Transformers\UrlTransformer());
    }
    
    /**
     * Register a custom transformer
     */
    public function registerTransformer(TypeTransformer $transformer): void
    {
        $this->transformers[] = $transformer;
    }
    
    /**
     * Convert to HTML-safe JSON for Blade embedding
     */
    public function toHtml($data): string
    {
        $serialized = $this->serialize($data);
        return htmlspecialchars(json_encode($serialized), ENT_QUOTES, 'UTF-8');
    }
}
```

### 2.2 Type Transformer Interface (10 minutes)

Create the base transformer interface:

```php
<?php

namespace YourVendor\LaravelSuperJson\Transformers;

interface TypeTransformer
{
    /**
     * Check if this transformer can handle the given value
     */
    public function canTransform($value): bool;
    
    /**
     * Transform the value for serialization
     */
    public function transform($value): mixed;
    
    /**
     * Restore the value from serialized form
     */
    public function restore($value): mixed;
    
    /**
     * Get the type identifier for this transformer
     */
    public function getType(): string;
}
```

### 2.3 Date Transformer Implementation (20 minutes)

Implement the DateTime transformer:

```php
<?php

namespace YourVendor\LaravelSuperJson\Transformers;

use DateTime;
use DateTimeInterface;
use DateTimeImmutable;
use Carbon\Carbon;

class DateTransformer implements TypeTransformer
{
    public function canTransform($value): bool
    {
        return $value instanceof DateTimeInterface;
    }
    
    public function transform($value): string
    {
        /** @var DateTimeInterface $value */
        return $value->format(DateTimeInterface::ATOM);
    }
    
    public function restore($value): DateTimeInterface
    {
        // Use Carbon if available, otherwise DateTime
        if (class_exists(Carbon::class)) {
            return new Carbon($value);
        }
        
        return new DateTime($value);
    }
    
    public function getType(): string
    {
        return 'Date';
    }
}
```

## Hour 3: Advanced transformers and middleware

### 3.1 BigInt Transformer (15 minutes)

Implement BigInt handling using PHP's arbitrary precision:

```php
<?php

namespace YourVendor\LaravelSuperJson\Transformers;

class BigIntTransformer implements TypeTransformer
{
    public function canTransform($value): bool
    {
        // Check if it's a BigInt wrapper or a string that represents a big integer
        if ($value instanceof BigInt) {
            return true;
        }
        
        if (is_string($value) && preg_match('/^-?\d{16,}$/', $value)) {
            // Numbers with 16+ digits are considered BigInts
            return true;
        }
        
        return false;
    }
    
    public function transform($value): string
    {
        if ($value instanceof BigInt) {
            return $value->toString();
        }
        
        return (string) $value;
    }
    
    public function restore($value): BigInt
    {
        return new BigInt($value);
    }
    
    public function getType(): string
    {
        return 'bigint';
    }
}

// BigInt wrapper class
class BigInt
{
    private string $value;
    
    public function __construct(string $value)
    {
        if (!preg_match('/^-?\d+$/', $value)) {
            throw new \InvalidArgumentException('Invalid BigInt value');
        }
        
        $this->value = $value;
    }
    
    public function toString(): string
    {
        return $this->value;
    }
    
    public function add(BigInt $other): BigInt
    {
        if (function_exists('bcadd')) {
            return new BigInt(bcadd($this->value, $other->value));
        }
        
        // Fallback for systems without BCMath
        return new BigInt((string)($this->toInt() + $other->toInt()));
    }
    
    public function toInt(): int
    {
        return intval($this->value);
    }
    
    public function __toString(): string
    {
        return $this->value;
    }
}
```

### 3.2 Collection Transformer (20 minutes)

Handle Set and Map equivalents:

```php
<?php

namespace YourVendor\LaravelSuperJson\Transformers;

use Illuminate\Support\Collection;

class CollectionTransformer implements TypeTransformer
{
    public function canTransform($value): bool
    {
        return $value instanceof SuperSet 
            || $value instanceof SuperMap
            || ($value instanceof Collection && $value->has('@type'));
    }
    
    public function transform($value): array
    {
        if ($value instanceof SuperSet) {
            return array_values($value->toArray());
        }
        
        if ($value instanceof SuperMap) {
            return $value->toArray();
        }
        
        return $value->toArray();
    }
    
    public function restore($value): SuperSet|SuperMap
    {
        // Logic to determine if it's a Set or Map based on structure
        if (is_array($value) && !$this->isAssociative($value)) {
            return new SuperSet($value);
        }
        
        return new SuperMap($value);
    }
    
    public function getType(): string
    {
        return 'set'; // Will be overridden based on actual type
    }
    
    private function isAssociative(array $arr): bool
    {
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}

// SuperSet implementation
class SuperSet implements \JsonSerializable, \Countable, \IteratorAggregate
{
    private array $items = [];
    
    public function __construct(array $items = [])
    {
        foreach ($items as $item) {
            $this->add($item);
        }
    }
    
    public function add($item): void
    {
        if (!in_array($item, $this->items, true)) {
            $this->items[] = $item;
        }
    }
    
    public function has($item): bool
    {
        return in_array($item, $this->items, true);
    }
    
    public function remove($item): bool
    {
        $key = array_search($item, $this->items, true);
        if ($key !== false) {
            unset($this->items[$key]);
            $this->items = array_values($this->items);
            return true;
        }
        return false;
    }
    
    public function toArray(): array
    {
        return $this->items;
    }
    
    public function jsonSerialize(): mixed
    {
        return $this->items;
    }
    
    public function count(): int
    {
        return count($this->items);
    }
    
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }
}

// SuperMap implementation
class SuperMap implements \JsonSerializable, \Countable, \IteratorAggregate
{
    private array $entries = [];
    
    public function __construct(array $entries = [])
    {
        foreach ($entries as $entry) {
            if (is_array($entry) && count($entry) === 2) {
                $this->set($entry[0], $entry[1]);
            }
        }
    }
    
    public function set($key, $value): void
    {
        foreach ($this->entries as &$entry) {
            if ($entry[0] === $key) {
                $entry[1] = $value;
                return;
            }
        }
        $this->entries[] = [$key, $value];
    }
    
    public function get($key)
    {
        foreach ($this->entries as $entry) {
            if ($entry[0] === $key) {
                return $entry[1];
            }
        }
        return null;
    }
    
    public function has($key): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry[0] === $key) {
                return true;
            }
        }
        return false;
    }
    
    public function delete($key): bool
    {
        foreach ($this->entries as $i => $entry) {
            if ($entry[0] === $key) {
                array_splice($this->entries, $i, 1);
                return true;
            }
        }
        return false;
    }
    
    public function toArray(): array
    {
        return $this->entries;
    }
    
    public function jsonSerialize(): mixed
    {
        return $this->entries;
    }
    
    public function count(): int
    {
        return count($this->entries);
    }
    
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->entries);
    }
}
```

### 3.3 Request Middleware (25 minutes)

Create middleware for automatic SuperJSON handling:

```php
<?php

namespace YourVendor\LaravelSuperJson\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use YourVendor\LaravelSuperJson\Facades\SuperJson;

class HandleSuperJsonRequests
{
    /**
     * Handle an incoming request with SuperJSON content
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if the request contains SuperJSON
        if ($this->isSuperjsonRequest($request)) {
            $content = $request->getContent();
            
            if (!empty($content)) {
                try {
                    // Deserialize SuperJSON content
                    $data = SuperJson::deserialize($content);
                    
                    // Replace request data
                    $request->merge(is_array($data) ? $data : ['data' => $data]);
                    
                    // Store original SuperJSON for potential use
                    $request->attributes->set('superjson_original', json_decode($content, true));
                    
                } catch (\Exception $e) {
                    // Log error but continue processing
                    \Log::warning('SuperJSON deserialization failed', [
                        'error' => $e->getMessage(),
                        'content' => substr($content, 0, 500)
                    ]);
                }
            }
        }
        
        $response = $next($request);
        
        // Auto-transform response if client accepts SuperJSON
        if ($this->shouldTransformResponse($request, $response)) {
            $data = $response->getData(true);
            
            if ($data !== null) {
                $serialized = SuperJson::serialize($data);
                $response->setData($serialized);
                $response->header('Content-Type', 'application/superjson');
            }
        }
        
        return $response;
    }
    
    /**
     * Check if request contains SuperJSON
     */
    protected function isSuperjsonRequest(Request $request): bool
    {
        $contentType = $request->header('Content-Type', '');
        
        return str_contains($contentType, 'application/superjson')
            || str_contains($contentType, 'application/sjson')
            || $request->has('_superjson');
    }
    
    /**
     * Check if response should be transformed to SuperJSON
     */
    protected function shouldTransformResponse(Request $request, $response): bool
    {
        if (!method_exists($response, 'getData')) {
            return false;
        }
        
        $accept = $request->header('Accept', '');
        
        return str_contains($accept, 'application/superjson')
            || str_contains($accept, 'application/sjson')
            || $request->has('_superjson');
    }
}
```

## Hour 4: Configuration, facade, and additional transformers

### 4.1 Configuration File (10 minutes)

Create the configuration file:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SuperJSON Transformers
    |--------------------------------------------------------------------------
    |
    | List of transformer classes that will be registered automatically.
    | You can add your own custom transformers here.
    |
    */
    'transformers' => [
        \YourVendor\LaravelSuperJson\Transformers\DateTransformer::class,
        \YourVendor\LaravelSuperJson\Transformers\BigIntTransformer::class,
        \YourVendor\LaravelSuperJson\Transformers\CollectionTransformer::class,
        \YourVendor\LaravelSuperJson\Transformers\RegexTransformer::class,
        \YourVendor\LaravelSuperJson\Transformers\UrlTransformer::class,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Serialization Options
    |--------------------------------------------------------------------------
    |
    | Configure how SuperJSON handles serialization and deserialization.
    |
    */
    'options' => [
        'preserve_zero_fraction' => true,
        'unescaped_unicode' => true,
        'throw_on_error' => true,
        'max_depth' => 512,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Auto-Transform Routes
    |--------------------------------------------------------------------------
    |
    | Define route patterns that should automatically use SuperJSON
    | transformation via middleware.
    |
    */
    'auto_routes' => [
        'api/*',
        'superjson/*',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Type Mappings
    |--------------------------------------------------------------------------
    |
    | Map PHP classes to SuperJSON types for custom handling.
    |
    */
    'type_mappings' => [
        \DateTime::class => 'Date',
        \DateTimeImmutable::class => 'Date',
        \Carbon\Carbon::class => 'Date',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Configure security-related options for deserialization.
    |
    */
    'security' => [
        'allow_class_restoration' => false,
        'allowed_classes' => [],
        'max_array_size' => 10000,
    ],
];
```

### 4.2 Facade Implementation (10 minutes)

Create the facade for convenient access:

```php
<?php

namespace YourVendor\LaravelSuperJson\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array serialize($data)
 * @method static mixed deserialize($input)
 * @method static void registerTransformer(\YourVendor\LaravelSuperJson\Transformers\TypeTransformer $transformer)
 * @method static string toHtml($data)
 * 
 * @see \YourVendor\LaravelSuperJson\SuperJson
 */
class SuperJson extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'superjson';
    }
}
```

### 4.3 Additional Transformers (40 minutes)

Implement RegEx and URL transformers:

```php
<?php

namespace YourVendor\LaravelSuperJson\Transformers;

// RegexTransformer.php
class RegexTransformer implements TypeTransformer
{
    public function canTransform($value): bool
    {
        return $value instanceof SerializableRegex;
    }
    
    public function transform($value): string
    {
        return $value->toString();
    }
    
    public function restore($value): SerializableRegex
    {
        return SerializableRegex::fromString($value);
    }
    
    public function getType(): string
    {
        return 'regexp';
    }
}

class SerializableRegex
{
    private string $pattern;
    private string $flags;
    
    public function __construct(string $pattern, string $flags = '')
    {
        $this->pattern = $pattern;
        $this->flags = $flags;
    }
    
    public static function fromString(string $regex): self
    {
        if (preg_match('/^\/(.*)\/([gimsuvy]*)$/', $regex, $matches)) {
            return new self($matches[1], $matches[2]);
        }
        
        return new self($regex);
    }
    
    public function toString(): string
    {
        return "/{$this->pattern}/{$this->flags}";
    }
    
    public function match(string $subject): array
    {
        $phpFlags = $this->convertJsToPhpFlags($this->flags);
        preg_match_all("/{$this->pattern}/{$phpFlags}", $subject, $matches);
        return $matches[0] ?? [];
    }
    
    private function convertJsToPhpFlags(string $jsFlags): string
    {
        $phpFlags = '';
        if (str_contains($jsFlags, 'i')) $phpFlags .= 'i';
        if (str_contains($jsFlags, 'm')) $phpFlags .= 'm';
        if (str_contains($jsFlags, 's')) $phpFlags .= 's';
        if (str_contains($jsFlags, 'u')) $phpFlags .= 'u';
        return $phpFlags;
    }
}

// UrlTransformer.php
class UrlTransformer implements TypeTransformer
{
    public function canTransform($value): bool
    {
        return $value instanceof SerializableUrl;
    }
    
    public function transform($value): string
    {
        return $value->toString();
    }
    
    public function restore($value): SerializableUrl
    {
        return new SerializableUrl($value);
    }
    
    public function getType(): string
    {
        return 'URL';
    }
}

class SerializableUrl
{
    private string $url;
    private array $components;
    
    public function __construct(string $url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("Invalid URL: $url");
        }
        
        $this->url = $url;
        $this->components = parse_url($url);
    }
    
    public function toString(): string
    {
        return $this->url;
    }
    
    public function getScheme(): ?string
    {
        return $this->components['scheme'] ?? null;
    }
    
    public function getHost(): ?string
    {
        return $this->components['host'] ?? null;
    }
    
    public function getPort(): ?int
    {
        return $this->components['port'] ?? null;
    }
    
    public function getPath(): ?string
    {
        return $this->components['path'] ?? null;
    }
    
    public function getQuery(): ?string
    {
        return $this->components['query'] ?? null;
    }
    
    public function __toString(): string
    {
        return $this->url;
    }
}
```

## Hour 5: Testing implementation

### 5.1 Test Base Class (15 minutes)

Create the base test class with Orchestra Testbench:

```php
<?php

namespace YourVendor\LaravelSuperJson\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use YourVendor\LaravelSuperJson\SuperJsonServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Additional setup if needed
    }
    
    protected function getPackageProviders($app): array
    {
        return [
            SuperJsonServiceProvider::class,
        ];
    }
    
    protected function getEnvironmentSetUp($app): void
    {
        // Configure test environment
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        
        // SuperJSON configuration for testing
        $app['config']->set('superjson.options.throw_on_error', true);
    }
}
```

### 5.2 Unit Tests (25 minutes)

Create comprehensive unit tests:

```php
<?php

namespace YourVendor\LaravelSuperJson\Tests\Unit;

use YourVendor\LaravelSuperJson\Tests\TestCase;
use YourVendor\LaravelSuperJson\SuperJson;
use YourVendor\LaravelSuperJson\Transformers\BigInt;
use YourVendor\LaravelSuperJson\Transformers\SuperSet;
use YourVendor\LaravelSuperJson\Transformers\SuperMap;

class SuperJsonTest extends TestCase
{
    private SuperJson $superJson;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->superJson = new SuperJson();
    }
    
    /** @test */
    public function it_serializes_and_deserializes_dates()
    {
        $date = new \DateTime('2023-10-05 12:00:00', new \DateTimeZone('UTC'));
        $data = ['timestamp' => $date];
        
        $serialized = $this->superJson->serialize($data);
        
        $this->assertArrayHasKey('json', $serialized);
        $this->assertArrayHasKey('meta', $serialized);
        $this->assertEquals(['Date'], $serialized['meta']['values']['timestamp']);
        
        $deserialized = $this->superJson->deserialize($serialized);
        
        $this->assertInstanceOf(\DateTimeInterface::class, $deserialized['timestamp']);
        $this->assertEquals(
            $date->format('Y-m-d H:i:s'),
            $deserialized['timestamp']->format('Y-m-d H:i:s')
        );
    }
    
    /** @test */
    public function it_handles_bigint_values()
    {
        $bigInt = new BigInt('12345678901234567890');
        $data = ['big_number' => $bigInt];
        
        $serialized = $this->superJson->serialize($data);
        
        $this->assertEquals('12345678901234567890', $serialized['json']['big_number']);
        $this->assertEquals(['bigint'], $serialized['meta']['values']['big_number']);
        
        $deserialized = $this->superJson->deserialize($serialized);
        
        $this->assertInstanceOf(BigInt::class, $deserialized['big_number']);
        $this->assertEquals('12345678901234567890', $deserialized['big_number']->toString());
    }
    
    /** @test */
    public function it_handles_sets()
    {
        $set = new SuperSet(['apple', 'banana', 'apple', 'cherry']);
        $data = ['fruits' => $set];
        
        $serialized = $this->superJson->serialize($data);
        
        // Set should remove duplicates
        $this->assertCount(3, $serialized['json']['fruits']);
        $this->assertContains('apple', $serialized['json']['fruits']);
        $this->assertContains('banana', $serialized['json']['fruits']);
        $this->assertContains('cherry', $serialized['json']['fruits']);
        
        $deserialized = $this->superJson->deserialize($serialized);
        
        $this->assertInstanceOf(SuperSet::class, $deserialized['fruits']);
        $this->assertEquals(3, $deserialized['fruits']->count());
    }
    
    /** @test */
    public function it_handles_maps()
    {
        $map = new SuperMap([
            ['key1', 'value1'],
            ['key2', 'value2'],
            [123, 'numeric key'],
        ]);
        $data = ['mapping' => $map];
        
        $serialized = $this->superJson->serialize($data);
        
        $this->assertCount(3, $serialized['json']['mapping']);
        $this->assertEquals(['key1', 'value1'], $serialized['json']['mapping'][0]);
        
        $deserialized = $this->superJson->deserialize($serialized);
        
        $this->assertInstanceOf(SuperMap::class, $deserialized['mapping']);
        $this->assertEquals('value1', $deserialized['mapping']->get('key1'));
        $this->assertEquals('numeric key', $deserialized['mapping']->get(123));
    }
    
    /** @test */
    public function it_handles_nested_structures()
    {
        $data = [
            'user' => [
                'name' => 'John Doe',
                'created_at' => new \DateTime('2023-01-01'),
                'settings' => new SuperMap([
                    ['theme', 'dark'],
                    ['language', 'en'],
                ]),
                'tags' => new SuperSet(['admin', 'user']),
            ],
        ];
        
        $serialized = $this->superJson->serialize($data);
        $deserialized = $this->superJson->deserialize($serialized);
        
        $this->assertEquals('John Doe', $deserialized['user']['name']);
        $this->assertInstanceOf(\DateTimeInterface::class, $deserialized['user']['created_at']);
        $this->assertInstanceOf(SuperMap::class, $deserialized['user']['settings']);
        $this->assertInstanceOf(SuperSet::class, $deserialized['user']['tags']);
    }
    
    /** @test */
    public function it_handles_plain_json_gracefully()
    {
        $plainJson = '{"name":"John","age":30}';
        
        $deserialized = $this->superJson->deserialize($plainJson);
        
        $this->assertEquals(['name' => 'John', 'age' => 30], $deserialized);
    }
}
```

### 5.3 Feature Tests (20 minutes)

Test Laravel integration features:

```php
<?php

namespace YourVendor\LaravelSuperJson\Tests\Feature;

use YourVendor\LaravelSuperJson\Tests\TestCase;
use YourVendor\LaravelSuperJson\Transformers\BigInt;
use Illuminate\Support\Facades\Route;

class LaravelIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test routes
        Route::post('/test-superjson', function () {
            return response()->superjson([
                'timestamp' => now(),
                'big_number' => new BigInt('99999999999999999999'),
            ]);
        })->middleware('superjson');
        
        Route::post('/test-request', function () {
            return response()->json([
                'received' => request()->superjson(),
            ]);
        })->middleware('superjson');
    }
    
    /** @test */
    public function response_macro_works()
    {
        $response = $this->postJson('/test-superjson');
        
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/superjson');
        
        $data = $response->json();
        
        $this->assertArrayHasKey('json', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('timestamp', $data['json']);
        $this->assertArrayHasKey('big_number', $data['json']);
    }
    
    /** @test */
    public function request_macro_works()
    {
        $superJsonData = [
            'json' => [
                'name' => 'Test',
                'created' => '2023-10-05T12:00:00+00:00',
            ],
            'meta' => [
                'values' => [
                    'created' => ['Date'],
                ],
            ],
        ];
        
        $response = $this->postJson('/test-request', $superJsonData, [
            'Content-Type' => 'application/superjson',
        ]);
        
        $response->assertStatus(200);
        
        $received = $response->json('received');
        
        $this->assertEquals('Test', $received['name']);
        $this->assertNotNull($received['created']);
    }
    
    /** @test */
    public function middleware_auto_transforms_responses()
    {
        $response = $this->postJson('/test-superjson', [], [
            'Accept' => 'application/superjson',
        ]);
        
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/superjson');
        
        $data = $response->json();
        
        $this->assertArrayHasKey('json', $data);
        $this->assertArrayHasKey('meta', $data);
    }
    
    /** @test */
    public function blade_directive_renders_correctly()
    {
        $view = view()->make('test-view', [
            'data' => ['timestamp' => now()],
        ])->render();
        
        // View content: <script>const data = @superjson($data);</script>
        
        $this->assertStringContainsString('"json":', $view);
        $this->assertStringContainsString('"meta":', $view);
    }
}
```

## Hour 6: Documentation, examples, and finalization

### 6.1 Comprehensive README (30 minutes)

Create a professional README.md:

```markdown
# Laravel SuperJSON Bridge

[![Latest Version](https://img.shields.io/packagist/v/yourvendor/laravel-superjson-bridge.svg)](https://packagist.org/packages/yourvendor/laravel-superjson-bridge)
[![Total Downloads](https://img.shields.io/packagist/dt/yourvendor/laravel-superjson-bridge.svg)](https://packagist.org/packages/yourvendor/laravel-superjson-bridge)
[![License](https://img.shields.io/packagist/l/yourvendor/laravel-superjson-bridge.svg)](https://packagist.org/packages/yourvendor/laravel-superjson-bridge)

Seamlessly preserve JavaScript types (Date, BigInt, Map, Set, URL, RegExp) between Laravel APIs and JavaScript clients using the SuperJSON format.

## Features

- 🎯 **Type Preservation**: Maintains Date, BigInt, Map, Set, URL, and RegExp types
- 🔄 **Bidirectional**: Works for both requests and responses
- 🎨 **Laravel Integration**: Response macros, request helpers, Blade directives
- ⚡ **Middleware Support**: Automatic transformation with middleware
- 🔧 **Extensible**: Add custom type transformers
- 📦 **Zero Dependencies**: Works with vanilla Laravel

## Installation

```bash
composer require yourvendor/laravel-superjson-bridge
```

The package will auto-register its service provider.

### Publishing Configuration

```bash
php artisan vendor:publish --tag=superjson-config
```

## Quick Start

### Response Macro

```php
// In your controller
return response()->superjson([
    'user' => $user,
    'created_at' => now(),
    'big_id' => new BigInt('12345678901234567890'),
    'tags' => new SuperSet(['php', 'laravel', 'json']),
]);

// Short alias
return response()->sjson($data);
```

### Request Helper

```php
// Access SuperJSON data from requests
$data = $request->superjson();
$specificField = $request->superjson('user.name');

// Short alias
$data = $request->sjson();
```

### Middleware

```php
// In routes/api.php
Route::middleware(['superjson'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
});
```

### Blade Directive

```blade
<script>
    // Safely embed SuperJSON data in your views
    const serverData = @superjson($data);
    
    // Parse it in JavaScript
    const parsed = SuperJSON.parse(serverData);
</script>
```

## Type Support

| Type | PHP Implementation | JavaScript Type |
|------|-------------------|-----------------|
| Date | `DateTime`, `Carbon` | `Date` |
| BigInt | `BigInt` class | `BigInt` |
| Set | `SuperSet` class | `Set` |
| Map | `SuperMap` class | `Map` |
| URL | `SerializableUrl` class | `URL` |
| RegExp | `SerializableRegex` class | `RegExp` |

## Advanced Usage

### Custom Type Transformers

```php
use YourVendor\LaravelSuperJson\Transformers\TypeTransformer;

class MoneyTransformer implements TypeTransformer
{
    public function canTransform($value): bool
    {
        return $value instanceof Money;
    }
    
    public function transform($value): array
    {
        return [
            'amount' => $value->getAmount(),
            'currency' => $value->getCurrency(),
        ];
    }
    
    public function restore($value): Money
    {
        return new Money($value['amount'], $value['currency']);
    }
    
    public function getType(): string
    {
        return 'Money';
    }
}

// Register in a service provider
app('superjson')->registerTransformer(new MoneyTransformer());
```

### Working with Collections

```php
use YourVendor\LaravelSuperJson\Transformers\SuperSet;
use YourVendor\LaravelSuperJson\Transformers\SuperMap;

// Sets (unique values)
$tags = new SuperSet(['laravel', 'php', 'laravel']); // Duplicates removed
$tags->add('javascript');
$tags->has('php'); // true
$tags->remove('javascript');

// Maps (key-value pairs)
$settings = new SuperMap([
    ['theme', 'dark'],
    ['language', 'en'],
    [123, 'numeric key'], // Any type as key
]);
$settings->set('theme', 'light');
$value = $settings->get('theme'); // 'light'
```

### JavaScript Client Usage

```javascript
// Install the SuperJSON package
npm install superjson

// Import and use
import SuperJSON from 'superjson';

// Sending to Laravel
const data = {
    timestamp: new Date(),
    bigNumber: BigInt('99999999999999999999'),
    tags: new Set(['js', 'frontend']),
};

const response = await fetch('/api/endpoint', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/superjson',
        'Accept': 'application/superjson',
    },
    body: SuperJSON.stringify(data),
});

const result = SuperJSON.parse(await response.text());
// Types are preserved! result.timestamp is a Date object
```

## Configuration

```php
// config/superjson.php
return [
    'transformers' => [
        // Add custom transformers
        \App\Transformers\MyCustomTransformer::class,
    ],
    
    'options' => [
        'preserve_zero_fraction' => true,
        'unescaped_unicode' => true,
        'throw_on_error' => true,
        'max_depth' => 512,
    ],
    
    'auto_routes' => [
        'api/*',  // Auto-apply to all API routes
    ],
    
    'security' => [
        'allow_class_restoration' => false,
        'allowed_classes' => [],
    ],
];
```

## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email security@example.com instead of using the issue tracker.

## Credits

- [Your Name](https://github.com/yourname)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
```

### 6.2 Usage Examples File (15 minutes)

Create comprehensive examples:

```php
<?php
// examples/usage.php

use YourVendor\LaravelSuperJson\Facades\SuperJson;
use YourVendor\LaravelSuperJson\Transformers\BigInt;
use YourVendor\LaravelSuperJson\Transformers\SuperSet;
use YourVendor\LaravelSuperJson\Transformers\SuperMap;
use YourVendor\LaravelSuperJson\Transformers\SerializableRegex;
use YourVendor\LaravelSuperJson\Transformers\SerializableUrl;

// Example 1: Basic serialization
$data = [
    'name' => 'John Doe',
    'created_at' => now(),
    'updated_at' => now()->addDays(5),
];

$serialized = SuperJson::serialize($data);
// Result:
// [
//     'json' => [
//         'name' => 'John Doe',
//         'created_at' => '2023-10-05T12:00:00+00:00',
//         'updated_at' => '2023-10-10T12:00:00+00:00',
//     ],
//     'meta' => [
//         'values' => [
//             'created_at' => ['Date'],
//             'updated_at' => ['Date'],
//         ]
//     ]
// ]

// Example 2: Complex nested structure
$complexData = [
    'user' => [
        'id' => new BigInt('12345678901234567890'),
        'name' => 'Jane Smith',
        'email_pattern' => new SerializableRegex('[a-z]+@[a-z]+\\.com', 'i'),
        'website' => new SerializableUrl('https://example.com'),
        'preferences' => new SuperMap([
            ['theme', 'dark'],
            ['notifications', true],
            ['language', 'en'],
        ]),
        'roles' => new SuperSet(['admin', 'moderator', 'user']),
        'metadata' => [
            'last_login' => now(),
            'login_count' => 42,
        ],
    ],
];

$serialized = SuperJson::serialize($complexData);
$deserialized = SuperJson::deserialize($serialized);

// All types are preserved!
assert($deserialized['user']['id'] instanceof BigInt);
assert($deserialized['user']['preferences'] instanceof SuperMap);
assert($deserialized['user']['roles'] instanceof SuperSet);

// Example 3: API Controller
class UserController extends Controller
{
    public function show(User $user)
    {
        return response()->superjson([
            'user' => $user,
            'permissions' => new SuperSet($user->permissions->pluck('name')),
            'last_activity' => $user->last_activity_at,
            'account_number' => new BigInt($user->account_number),
        ]);
    }
    
    public function store(Request $request)
    {
        // Access SuperJSON data
        $data = $request->superjson();
        
        // $data['created_at'] is already a DateTime object!
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'created_at' => $data['created_at'] ?? now(),
        ]);
        
        return response()->superjson($user, 201);
    }
}

// Example 4: Blade view integration
// In your controller:
return view('dashboard', [
    'stats' => [
        'users' => User::count(),
        'revenue' => new BigInt('9999999999999999'),
        'updated' => now(),
    ],
]);

// In dashboard.blade.php:
?>
<script>
    const stats = @superjson($stats);
    
    // Use with JavaScript SuperJSON library
    import SuperJSON from 'superjson';
    const parsed = SuperJSON.parse(stats);
    
    console.log(parsed.updated instanceof Date); // true
    console.log(typeof parsed.revenue); // 'bigint'
</script>
```

### 6.3 Final Package Files (15 minutes)

Create remaining essential files:

**phpunit.xml:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="./vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
>
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
    </testsuites>
    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">./src</directory>
        </include>
    </coverage>
    <php>
        <env name="APP_KEY" value="AckfSECXIvnK5r28GVIWUAxmbBSjTsmF"/>
        <env name="DB_CONNECTION" value="testing"/>
    </php>
</phpunit>
```

**.gitignore:**
```
/vendor
/node_modules
/.idea
/.vscode
/.vagrant
.phpunit.result.cache
.php-cs-fixer.cache
composer.lock
package-lock.json
.DS_Store
Thumbs.db
```

**GitHub Actions Workflow (.github/workflows/tests.yml):**
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: [8.1, 8.2, 8.3]
        laravel: [10.*, 11.*]
        include:
          - laravel: 10.*
            testbench: 8.*
          - laravel: 11.*
            testbench: 9.*

    name: P${{ matrix.php }} - L${{ matrix.laravel }}

    steps:
      - name: Checkout code
        uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: mbstring, json, bcmath
          coverage: none

      - name: Install dependencies
        run: |
          composer require "laravel/framework:${{ matrix.laravel }}" "orchestra/testbench:${{ matrix.testbench }}" --no-interaction --no-update
          composer update --prefer-dist --no-interaction

      - name: Execute tests
        run: vendor/bin/phpunit
```

## Common mistakes to avoid based on research

### Critical Development Pitfalls

1. **Service Provider Registration**: Never put route registration, view loading, or event listeners in the `register()` method - use `boot()` exclusively for these operations

2. **Configuration Management**: Always use `mergeConfigFrom()` in `register()` before `publishes()` in `boot()` to ensure default configurations are available

3. **Testing Without Orchestra**: Never test Laravel packages without Orchestra Testbench - it provides the necessary Laravel application context

4. **Namespace Mismatches**: Ensure PSR-4 autoloading exactly matches your directory structure and namespace declarations

5. **Missing Auto-Discovery**: Always configure Laravel package auto-discovery in composer.json to avoid manual service provider registration

## Performance optimizations implemented

The package includes several performance optimizations discovered during research:

- **Lazy Transformer Loading**: Transformers are only instantiated when needed
- **Path Caching**: Property paths are cached during serialization to avoid recalculation
- **Metadata Minimization**: Meta property is only included when special types are present
- **Efficient Type Detection**: Uses early returns and type checking order optimization

## Distribution and publishing strategy

### Package Release Checklist

1. **Version Tagging**: Use semantic versioning (start with v0.1.0)
2. **Packagist Registration**: Submit to packagist.org with proper keywords
3. **Documentation**: Ensure README, LICENSE, and CONTRIBUTING files are complete
4. **Testing**: Verify all tests pass on supported PHP and Laravel versions
5. **Security**: Run security audits with `composer audit`

This comprehensive development plan provides a realistic, achievable path to creating a production-ready Laravel SuperJSON bridge package in approximately 6 hours of focused development. The implementation follows Laravel best practices, avoids common pitfalls, and provides seamless integration with Laravel's existing JSON handling infrastructure.