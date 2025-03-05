<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

/**
 * Allowed parent object type of page object
 * Should be migrated to enum with PHP 8.1
 */
class ParentType
{
    public const QUESTION = 'qpl';

    public const CASES = [self::QUESTION];

    private string $value;

    public static function from(string $value): self
    {
        return new self($value);
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
