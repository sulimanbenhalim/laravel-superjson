<?php

namespace SulimanBenhalim\LaravelSuperJson\Transformers;

use SulimanBenhalim\LaravelSuperJson\DataTypes\BigInt;

class BigIntTransformer implements TypeTransformer
{
    public function canTransform($value): bool
    {
        if ($value instanceof BigInt) {
            return true;
        }

        if (is_string($value) && preg_match('/^-?\d{16,}$/', $value)) {
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
