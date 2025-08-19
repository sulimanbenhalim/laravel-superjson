<?php

namespace SulimanBenhalim\LaravelSuperJson\DataTypes;

class BigInt
{
    private string $value;

    public function __construct(string $value)
    {
        if (! preg_match('/^-?\d+$/', $value)) {
            throw new \InvalidArgumentException('Invalid BigInt value');
        }

        $this->value = $value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function add(BigInt $other): BigInt
    {
        if (function_exists('bcadd')) {
            return new BigInt(bcadd($this->value, $other->value));
        }

        return new BigInt((string) ($this->toInt() + $other->toInt()));
    }

    public function subtract(BigInt $other): BigInt
    {
        if (function_exists('bcsub')) {
            return new BigInt(bcsub($this->value, $other->value));
        }

        return new BigInt((string) ($this->toInt() - $other->toInt()));
    }

    public function multiply(BigInt $other): BigInt
    {
        if (function_exists('bcmul')) {
            return new BigInt(bcmul($this->value, $other->value));
        }

        return new BigInt((string) ($this->toInt() * $other->toInt()));
    }

    public function divide(BigInt $other): BigInt
    {
        if (function_exists('bcdiv')) {
            return new BigInt(bcdiv($this->value, $other->value, 0));
        }

        return new BigInt((string) intval($this->toInt() / $other->toInt()));
    }

    public function modulo(BigInt $other): BigInt
    {
        if (function_exists('bcmod')) {
            return new BigInt(bcmod($this->value, $other->value));
        }

        return new BigInt((string) ($this->toInt() % $other->toInt()));
    }

    public function compare(BigInt $other): int
    {
        if (function_exists('bccomp')) {
            return bccomp($this->value, $other->value);
        }

        $thisInt = $this->toInt();
        $otherInt = $other->toInt();

        return $thisInt <=> $otherInt;
    }

    public function equals(BigInt $other): bool
    {
        return $this->compare($other) === 0;
    }

    public function isPositive(): bool
    {
        return $this->value[0] !== '-' && $this->value !== '0';
    }

    public function isNegative(): bool
    {
        return $this->value[0] === '-';
    }

    public function isZero(): bool
    {
        return $this->value === '0';
    }

    public function absolute(): BigInt
    {
        if ($this->isNegative()) {
            return new BigInt(substr($this->value, 1));
        }

        return new BigInt($this->value);
    }

    private function toInt()
    {
        $int = (int) $this->value;

        // Check for overflow - if conversion back to string doesn't match, we've lost precision
        if ((string) $int !== $this->value) {
            throw new \RuntimeException('BigInt value is too large for int conversion: '.$this->value);
        }

        return $int;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
