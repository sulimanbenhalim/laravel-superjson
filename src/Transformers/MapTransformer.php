<?php

namespace SulimanBenhalim\LaravelSuperJson\Transformers;

use SulimanBenhalim\LaravelSuperJson\DataTypes\SuperMap;

class MapTransformer implements TypeTransformer
{
    public function canTransform($value): bool
    {
        return $value instanceof SuperMap;
    }

    public function transform($value): array
    {
        return $value->toArray();
    }

    public function restore($value): SuperMap
    {
        return new SuperMap($value);
    }

    public function getType(): string
    {
        return 'map';
    }
}
