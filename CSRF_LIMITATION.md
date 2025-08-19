# CSRF Limitation with POST Requests

> Known limitation when using SuperJSON with CSRF-protected web routes

## The Issue

Laravel's CSRF middleware expects traditional form submissions or properly configured session-based requests. When sending SuperJSON data via POST requests with CSRF protection, you may encounter:

- **419 CSRF Token Mismatch errors**
- **Session cookie not properly established** 
- **Browser-server session context mismatch**

## What Works

| Feature | Status | Notes |
|---------|--------|-------|
| GET requests | **Works** | All SuperJSON responses work flawlessly |
| Blade directives | **Works** | Server-side SuperJSON rendering works |
| JavaScript integration | **Works** | SuperJSON parsing and display work |
| API routes | **Works** | No CSRF protection, works seamlessly |
| POST with CSRF | **Limited** | May fail with token mismatch errors |

## Solutions

### 1. Use API Routes (Recommended)

Use API routes which don't have CSRF protection:

```php
// routes/api.php
Route::post('/api/data', function() {
    $data = request()->superjson();
    return response()->superjson($result);
});
```

```javascript
// Frontend
await fetch('/api/data', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/superjson',
        'Accept': 'application/superjson'
    },
    body: SuperJSON.stringify(data)
});
```

### 2. CSRF Exception

Add routes to CSRF exceptions:

```php
// app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    '/superjson-endpoint',
    '/api/superjson/*',
];
```

### 3. Form Data Approach

Send SuperJSON as form data:

```javascript
const formData = new FormData();
formData.append('_token', csrfToken);
formData.append('superjson_data', SuperJSON.stringify(data));

fetch('/endpoint', {
    method: 'POST',
    body: formData
});
```

```php
// Backend
$data = request()->input('superjson_data');
$parsed = app('superjson')->deserialize($data);
```

## Impact Assessment

**Core functionality is unaffected:**

- Server-side serialization/deserialization  
- Response formatting in all scenarios  
- Data type preservation  
- Laravel integration (non-CSRF scenarios)

**Recommended architecture:**
- Use **API routes** for SuperJSON POST requests
- Use **web routes** for traditional form submissions
- Use **Blade directives** for server-to-client data transfer

## Future Roadmap

- Custom CSRF middleware for SuperJSON content types
- Enhanced session management for JSON requests  
- Built-in API route helpers