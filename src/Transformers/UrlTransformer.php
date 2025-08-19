<?php

namespace SulimanBenhalim\LaravelSuperJson\Transformers;

use SulimanBenhalim\LaravelSuperJson\DataTypes\SerializableUrl;

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
