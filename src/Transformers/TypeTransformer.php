<?php

namespace SulimanBenhalim\LaravelSuperJson\Transformers;

interface TypeTransformer
{
    /**
     * Check if this transformer can handle the given value
     */
    public function canTransform($value): bool;

    /**
     * Transform the value for serialization
     */
    public function transform($value): mixed;

    /**
     * Restore the value from serialized form
     */
    public function restore($value): mixed;

    /**
     * Get the type identifier for this transformer
     */
    public function getType(): string;
}
