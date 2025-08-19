<?php

namespace SulimanBenhalim\LaravelSuperJson\DataTypes;

class SerializableUrl
{
    private string $url;

    private array $components;

    public function __construct(string $url)
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("Invalid URL: $url");
        }

        $this->url = $url;
        $this->components = parse_url($url);
    }

    public function toString(): string
    {
        return $this->url;
    }

    public function getScheme(): ?string
    {
        return $this->components['scheme'] ?? null;
    }

    public function getHost(): ?string
    {
        return $this->components['host'] ?? null;
    }

    public function getPort(): ?int
    {
        return $this->components['port'] ?? null;
    }

    public function getPath(): ?string
    {
        return $this->components['path'] ?? null;
    }

    public function getQuery(): ?string
    {
        return $this->components['query'] ?? null;
    }

    public function getFragment(): ?string
    {
        return $this->components['fragment'] ?? null;
    }

    public function getUser(): ?string
    {
        return $this->components['user'] ?? null;
    }

    public function getPass(): ?string
    {
        return $this->components['pass'] ?? null;
    }

    public function __toString(): string
    {
        return $this->url;
    }
}
