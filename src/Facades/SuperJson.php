<?php

namespace SulimanBenhalim\LaravelSuperJson\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array serialize($data)
 * @method static mixed deserialize($input)
 * @method static void registerTransformer(\SulimanBenhalim\LaravelSuperJson\Transformers\TypeTransformer $transformer)
 * @method static string toHtml($data)
 *
 * @see \SulimanBenhalim\LaravelSuperJson\SuperJson
 */
class SuperJson extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'superjson';
    }
}
