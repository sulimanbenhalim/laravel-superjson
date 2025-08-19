<?php

namespace SulimanBenhalim\LaravelSuperJson\Transformers;

use Carbon\Carbon;
use DateTime;
use DateTimeInterface;

/**
 * Transformer for DateTime objects to ISO 8601 format
 */
class DateTransformer implements TypeTransformer
{
    /**
     * Check if value is a DateTime instance that can be transformed
     */
    public function canTransform($value): bool
    {
        return $value instanceof DateTimeInterface;
    }

    /**
     * Transform DateTime to ISO 8601 string format
     */
    public function transform($value): string
    {
        /** @var DateTimeInterface $value */
        return $value->format(DateTimeInterface::ATOM);
    }

    /**
     * Restore ISO 8601 string back to DateTime instance (prefers Carbon if available)
     */
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
