<?php

namespace SulimanBenhalim\LaravelSuperJson\DataTypes;

class SuperSet implements \Countable, \IteratorAggregate, \JsonSerializable
{
    private array $items = [];

    public function __construct(array $items = [])
    {
        foreach ($items as $item) {
            $this->add($item);
        }
    }

    public function add($item): void
    {
        if (! in_array($item, $this->items, true)) {
            $this->items[] = $item;
        }
    }

    public function has($item): bool
    {
        return in_array($item, $this->items, true);
    }

    public function remove($item): bool
    {
        $key = array_search($item, $this->items, true);
        if ($key !== false) {
            unset($this->items[$key]);
            $this->items = array_values($this->items);

            return true;
        }

        return false;
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function jsonSerialize(): mixed
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }
}
