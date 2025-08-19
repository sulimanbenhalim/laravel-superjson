<?php

namespace SulimanBenhalim\LaravelSuperJson\Transformers;

use SulimanBenhalim\LaravelSuperJson\DataTypes\SerializableError;

class ErrorTransformer implements TypeTransformer
{
    public function canTransform($value): bool
    {
        return $value instanceof \Throwable;
    }

    public function transform($value): array
    {
        return [
            'name' => get_class($value),
            'message' => $value->getMessage(),
            'code' => $value->getCode(),
            'file' => $value->getFile(),
            'line' => $value->getLine(),
            'trace' => $this->sanitizeTrace($value->getTrace()),
            'previous' => $value->getPrevious() ? $this->transform($value->getPrevious()) : null,
        ];
    }

    public function restore($value): SerializableError
    {
        return new SerializableError(
            $value['name'],
            $value['message'],
            $value['code'],
            $value['file'],
            $value['line'],
            $value['trace'],
            $value['previous'] ? $this->restore($value['previous']) : null
        );
    }

    public function getType(): string
    {
        return 'Error';
    }

    /**
     * Sanitize stack trace to avoid serialization issues
     */
    protected function sanitizeTrace(array $trace): array
    {
        return array_map(function ($item) {
            return [
                'file' => $item['file'] ?? '[internal]',
                'line' => $item['line'] ?? 0,
                'function' => $item['function'] ?? '',
                'class' => $item['class'] ?? '',
                'type' => $item['type'] ?? '',
            ];
        }, array_slice($trace, 0, 10)); // Limit to first 10 frames
    }
}
