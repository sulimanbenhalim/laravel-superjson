<?php
// examples/usage.php

use SulimanBenhalim\LaravelSuperJson\DataTypes\BigInt;
use SulimanBenhalim\LaravelSuperJson\DataTypes\SerializableRegex;
use SulimanBenhalim\LaravelSuperJson\DataTypes\SerializableUrl;
use SulimanBenhalim\LaravelSuperJson\DataTypes\SuperMap;
use SulimanBenhalim\LaravelSuperJson\DataTypes\SuperSet;
use SulimanBenhalim\LaravelSuperJson\Facades\SuperJson;

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