# Laravel SuperJSON

[![GitHub License](https://img.shields.io/github/license/sulimanbenhalim/laravel-superjson)](https://github.com/sulimanbenhalim/laravel-superjson/blob/main/LICENSE.md)
[![PHP Version Support](https://img.shields.io/badge/php-%3E%3D%208.2-blue)](https://www.php.net/supported-versions.php)
[![Laravel Version Support](https://img.shields.io/badge/laravel-%3E%3D%2011.0-red)](https://laravel.com/docs/11.x/releases)
[![Beta Version](https://img.shields.io/badge/status-beta-orange)](https://github.com/sulimanbenhalim/laravel-superjson)

> Type-preserving JSON serialization for Laravel APIs and JavaScript

**⚠️ BETA VERSION**: This package has known limitations with CSRF-protected POST requests. See [CSRF_LIMITATION.md](CSRF_LIMITATION.md) for details. Use API routes for POST requests.

## Install

```bash
composer require sulimanbenhalim/laravel-superjson
```

## Usage

### Response Macros
```php
// Type-preserving API responses
return response()->superjson([
    'user' => $user,
    'created_at' => now(),
    'big_id' => new BigInt('12345678901234567890'),
    'tags' => new SuperSet(['php', 'laravel']),
]);

// Short alias
return response()->sjson($data);
```

### Request Helpers
```php
// Parse SuperJSON from incoming requests
$data = request()->superjson();
$user = request()->superjson('user.name');

// Short alias  
$data = request()->sjson();
```

### Blade Directives
```blade
<script>
    const data = @superjson($serverData);
    // JavaScript types preserved: Date, BigInt, Set, Map, etc.
</script>
```

### Middleware
```php
// routes/api.php
Route::middleware(['superjson'])->group(function () {
    Route::get('/users', UserController::class);
    Route::post('/users', UserController::class);
});
```

## Type Support

| PHP Type | JavaScript Type | Implementation |
|----------|----------------|----------------|
| DateTime, Carbon | Date | Built-in |
| BigInt class | BigInt | Built-in |
| SuperSet class | Set | Built-in |
| SuperMap class | Map | Built-in |
| SerializableUrl class | URL | Built-in |
| SerializableRegex class | RegExp | Built-in |
| Exception, Throwable | Error | Built-in |

## POST Requests

**Web routes with CSRF protection have limitations.** See [CSRF_LIMITATION.md](CSRF_LIMITATION.md) for details.

**Recommended approach for POST requests:**
```php
// Use API routes (no CSRF protection)
Route::post('/api/data', function() {
    $data = request()->superjson();
    return response()->superjson($result);
});
```

## JavaScript Integration

Install the [SuperJSON](https://github.com/flightcontrolhq/superjson) JavaScript library:

```bash
npm install superjson
```

```javascript
import SuperJSON from 'superjson';

// Receiving data (always works)
const response = await fetch('/api/users');
const data = SuperJSON.parse(await response.text());
// data.created_at is a Date object

// Sending data (use API routes)
const payload = {
    timestamp: new Date(),
    bigNumber: BigInt('999999999999999999'),
    tags: new Set(['frontend', 'api'])
};

await fetch('/api/users', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/superjson',
        'Accept': 'application/superjson'
    },
    body: SuperJSON.stringify(payload)
});
```

## Data Types Usage

### BigInt
```php
use SulimanBenhalim\LaravelSuperJson\DataTypes\BigInt;

$bigNumber = new BigInt('12345678901234567890');
$bigNumber->toString(); // "12345678901234567890" 
$bigNumber->toInt();    // Converts to int if safe
```

### Collections
```php
use SulimanBenhalim\LaravelSuperJson\DataTypes\{SuperSet, SuperMap};

// Sets (unique values)
$tags = new SuperSet(['php', 'laravel', 'php']); // 'php' appears once
$tags->add('javascript');
$tags->has('php');     // true
$tags->remove('php');

// Maps (key-value pairs with any key type)
$config = new SuperMap([
    ['theme', 'dark'],
    ['timeout', 300],
    [123, 'numeric key']
]);
$config->set('theme', 'light');
$theme = $config->get('theme'); // 'light'
```

### URLs and RegExp
```php
use SulimanBenhalim\LaravelSuperJson\DataTypes\{SerializableUrl, SerializableRegex};

$url = new SerializableUrl('https://example.com/path?q=search');
$url->getUrl();    // Returns parsed URL components

$pattern = new SerializableRegex('/user-(\d+)/', 'gi');
$pattern->getPattern(); // '/user-(\d+)/'
$pattern->getFlags();   // 'gi'
```

## Custom Transformers

```php
use SulimanBenhalim\LaravelSuperJson\Transformers\TypeTransformer;

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

// Register in service provider
app('superjson')->registerTransformer(new MoneyTransformer());
```

## Configuration

```bash
php artisan vendor:publish --tag=superjson-config
```

<details>
<summary><strong>All Settings</strong></summary>

```php
// config/superjson.php
return [
    'transformers' => [
        // Custom transformer classes
    ],
    
    'options' => [
        'preserve_zero_fraction' => true,
        'unescaped_unicode' => true,
        'throw_on_error' => true,
        'max_depth' => 512,
    ],
    
    'auto_routes' => [
        'api/*',        // Auto-apply to API routes
        'superjson/*',  // Custom route patterns
    ],
    
    'type_mappings' => [
        DateTime::class => 'Date',
        DateTimeImmutable::class => 'Date',
        'Carbon\Carbon' => 'Date',
    ],
    
    'security' => [
        'allow_class_restoration' => false,
        'allowed_classes' => [],
        'max_array_size' => 10000,
    ],
];
```

| Setting | Default | Description |
|---------|---------|-------------|
| `transformers` | Built-in types | Additional transformer classes |
| `preserve_zero_fraction` | `true` | Keep .0 in numbers |
| `unescaped_unicode` | `true` | Unicode handling |
| `throw_on_error` | `true` | Error handling behavior |
| `max_depth` | `512` | Nesting limit |
| `auto_routes` | `['api/*']` | Routes for automatic middleware |
| `allow_class_restoration` | `false` | Security: prevent class instantiation |
| `max_array_size` | `10000` | Array size limit |

</details>

## Testing

```bash
composer test
```

## Laravel Version Compatibility

| Laravel Version | Package Version |
|-----------------|-----------------|
| 11.x            | 0.9.x-beta      |
| 12.x            | 0.9.x-beta      |

**Note**: Version 1.0 will be released once CSRF limitations are resolved.

## Security

If you discover any security issues, please email soliman.benhalim@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.