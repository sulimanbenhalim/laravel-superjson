<?php

namespace SulimanBenhalim\LaravelSuperJson\DataTypes;

/**
 * Serializable representation of PHP errors/exceptions
 */
class SerializableError implements \JsonSerializable
{
    public string $name;

    public string $message;

    public int|string $code;

    public string $file;

    public int $line;

    public array $trace;

    public ?SerializableError $previous;

    public function __construct(
        string $name,
        string $message,
        int|string $code,
        string $file,
        int $line,
        array $trace = [],
        ?SerializableError $previous = null
    ) {
        $this->name = $name;
        $this->message = $message;
        $this->code = $code;
        $this->file = $file;
        $this->line = $line;
        $this->trace = $trace;
        $this->previous = $previous;
    }

    /**
     * Get the exception class name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the error message
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get the error code
     */
    public function getCode(): int|string
    {
        return $this->code;
    }

    /**
     * Get the file where the error occurred
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Get the line number where the error occurred
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * Get the stack trace
     */
    public function getTrace(): array
    {
        return $this->trace;
    }

    /**
     * Get the previous exception if any
     */
    public function getPrevious(): ?SerializableError
    {
        return $this->previous;
    }

    /**
     * Convert to a readable string format
     */
    public function toString(): string
    {
        $previous = $this->previous ? "\nPrevious: ".$this->previous->toString() : '';

        return sprintf(
            '%s: %s in %s:%d%s',
            $this->name,
            $this->message,
            $this->file,
            $this->line,
            $previous
        );
    }

    /**
     * Create a new exception instance (for compatibility)
     * Note: This creates a generic Exception, not the original type
     */
    public function toException(): \Exception
    {
        $previous = $this->previous ? $this->previous->toException() : null;

        return new \Exception($this->message, is_int($this->code) ? $this->code : 0, $previous);
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'message' => $this->message,
            'code' => $this->code,
            'file' => $this->file,
            'line' => $this->line,
            'trace' => $this->trace,
            'previous' => $this->previous,
        ];
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
