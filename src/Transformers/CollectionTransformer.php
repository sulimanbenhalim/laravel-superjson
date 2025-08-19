<?php

namespace SulimanBenhalim\LaravelSuperJson\Transformers;

use SulimanBenhalim\LaravelSuperJson\DataTypes\SuperSet;

class CollectionTransformer implements TypeTransformer
{
    public function canTransform($value): bool
    {
        return $value instanceof SuperSet;
    }

    public function transform($value): array
    {
        return array_values($value->toArray());
    }

    public function restore($value): SuperSet
    {
        return new SuperSet($value);
    }

    public function getType(): string
    {
        return 'set';
    }
}
