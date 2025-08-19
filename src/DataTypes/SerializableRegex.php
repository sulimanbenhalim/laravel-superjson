<?php

namespace SulimanBenhalim\LaravelSuperJson\DataTypes;

class SerializableRegex
{
    private string $pattern;

    private string $flags;

    public function __construct(string $pattern, string $flags = '')
    {
        $this->pattern = $pattern;
        $this->flags = $flags;
    }

    public static function fromString(string $regex): self
    {
        if (preg_match('/^\/(.*)\/([gimsuvy]*)$/', $regex, $matches)) {
            return new self($matches[1], $matches[2]);
        }

        return new self($regex);
    }

    public function toString(): string
    {
        return "/{$this->pattern}/{$this->flags}";
    }

    public function match(string $subject): array
    {
        $phpFlags = $this->convertJsToPhpFlags($this->flags);
        preg_match_all("/{$this->pattern}/{$phpFlags}", $subject, $matches);

        return $matches[0] ?? [];
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getFlags(): string
    {
        return $this->flags;
    }

    private function convertJsToPhpFlags(string $jsFlags): string
    {
        $phpFlags = '';
        if (str_contains($jsFlags, 'i')) {
            $phpFlags .= 'i';
        }
        if (str_contains($jsFlags, 'm')) {
            $phpFlags .= 'm';
        }
        if (str_contains($jsFlags, 's')) {
            $phpFlags .= 's';
        }
        if (str_contains($jsFlags, 'u')) {
            $phpFlags .= 'u';
        }

        return $phpFlags;
    }
}
