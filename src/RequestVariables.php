<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

use ILIAS\HTTP\Wrapper\ArrayBasedRequestWrapper;
use ILIAS\Refinery\Factory as Refinery;

class RequestVariables
{
    private ArrayBasedRequestWrapper $wrapper;
    private Refinery $refinery;

    public function __construct(ArrayBasedRequestWrapper $wrapper, Refinery $refinery)
    {
        $this->wrapper = $wrapper;
        $this->refinery = $refinery;
    }

    public function has(string $key): bool
    {
        return $this->wrapper->has($key);
    }

    public function bool(string $key, ?bool $default = null): ?bool
    {
        if ($this->wrapper->has($key)) {
            $value = $this->wrapper->retrieve($key, $this->refinery->kindlyTo()->string());
            if ($value === '') {
                return $default;
            }
            return (bool) $value;
        }
        return $default;
    }

    public function integer(string $key, ?int $default = null): ?int
    {
        if ($this->wrapper->has($key)) {
            $value = $this->wrapper->retrieve($key, $this->refinery->kindlyTo()->string());
            if ($value === '') {
                return $default;
            }
            return (int) $value;
        }
        return $default;
    }

    public function float(string $key, ?string $default = null): ?float
    {
        if ($this->wrapper->has($key)) {
            $value =  $this->wrapper->retrieve($key, $this->refinery->kindlyTo()->string());
            if ($value === '') {
                return $default;
            }
            return (float) $value;
        }
        return $default;
    }

    public function string(string $key, ?string $default = null): ?string
    {
        if ($this->wrapper->has($key)) {
            return $this->wrapper->retrieve($key, $this->refinery->kindlyTo()->string());
        }
        return $default;
    }
}
