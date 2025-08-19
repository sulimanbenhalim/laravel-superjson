<?php

namespace SulimanBenhalim\LaravelSuperJson\DataTypes;

class SuperMap implements \Countable, \IteratorAggregate, \JsonSerializable
{
    private array $entries = [];

    public function __construct(array $entries = [])
    {
        foreach ($entries as $entry) {
            if (is_array($entry) && count($entry) === 2) {
                $this->set($entry[0], $entry[1]);
            }
        }
    }

    public function set($key, $value): void
    {
        foreach ($this->entries as &$entry) {
            if ($entry[0] === $key) {
                $entry[1] = $value;

                return;
            }
        }
        $this->entries[] = [$key, $value];
    }

    public function get($key)
    {
        foreach ($this->entries as $entry) {
            if ($entry[0] === $key) {
                return $entry[1];
            }
        }

        return null;
    }

    public function has($key): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry[0] === $key) {
                return true;
            }
        }

        return false;
    }

    public function delete($key): bool
    {
        foreach ($this->entries as $i => $entry) {
            if ($entry[0] === $key) {
                array_splice($this->entries, $i, 1);

                return true;
            }
        }

        return false;
    }

    public function toArray(): array
    {
        return $this->entries;
    }

    public function jsonSerialize(): mixed
    {
        return $this->entries;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->entries);
    }
}
