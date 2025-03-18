<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

/**
 * Context for counting the usages of a medium
 * Should be migrated to enum with PHP 8.1
 */
class LimitContext
{
    public const USER = 'user';
    public const SESSION = 'session';
    public const TESTPASS = 'testpass';

    public const CASES = [self::USER, self::SESSION, self::TESTPASS];

    private string $value;

    public static function from(string $value): self
    {
        return new self($value);
    }

    public static function tryFrom(string $value): ?self
    {
        return in_array($value, self::CASES) ? new self($value) : null;
    }

    public function value(): string
    {
        return $this->value;
    }

    private function __construct(string $value)
    {
        if (!in_array($value, self::CASES)) {
            throw new \ValueError("value '$value' not allowed for " . __class__);
        }
        $this->value = $value;
    }
}
