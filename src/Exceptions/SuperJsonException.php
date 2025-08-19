<?php

namespace SulimanBenhalim\LaravelSuperJson\Exceptions;

use Exception;

class SuperJsonException extends Exception
{
    /**
     * Error codes for different types of failures
     */
    public const SERIALIZATION_FAILED = 1001;

    public const DESERIALIZATION_FAILED = 1002;

    public const INVALID_INPUT = 1003;

    public const SECURITY_VIOLATION = 1004;

    public const CLASS_RESTORATION_DENIED = 1005;

    public const TRANSFORMER_ERROR = 1006;

    public const CONFIGURATION_ERROR = 1007;

    /**
     * Additional context for the exception
     */
    protected array $context = [];

    /**
     * Create a new SuperJSON exception with context
     */
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get the exception context
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Add context to the exception
     */
    public function addContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * Create a security violation exception
     */
    public static function securityViolation(string $message, array $context = []): self
    {
        return new self($message, self::SECURITY_VIOLATION, null, $context);
    }

    /**
     * Create a deserialization failed exception
     */
    public static function deserializationFailed(string $message, array $context = []): self
    {
        return new self($message, self::DESERIALIZATION_FAILED, null, $context);
    }

    /**
     * Create a serialization failed exception
     */
    public static function serializationFailed(string $message, array $context = []): self
    {
        return new self($message, self::SERIALIZATION_FAILED, null, $context);
    }

    /**
     * Create an invalid input exception
     */
    public static function invalidInput(string $message, array $context = []): self
    {
        return new self($message, self::INVALID_INPUT, null, $context);
    }
}
