<?php

namespace SulimanBenhalim\LaravelSuperJson\Transformers;

use SulimanBenhalim\LaravelSuperJson\DataTypes\SerializableRegex;

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
