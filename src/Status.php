<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

/**
 * Playing status of a medium
 * Should be migrated to enum with PHP 8.1
 */
class Status
{
    /** @const playing can be started, button action is 'start' */
    public const START = 'start';

    /** @const playing should be immediately continued, e.g. at page reload when pause is forbidden  */
    public const PLAY = 'playing';

    /** @const  playing is paused, button action is 'continue'  */
    public const PAUSE = 'pause';

    /** @const playing limit is reached, button is not shown  */
    public const LIMIT = 'limit';

    public const CASES = [self::START, self::PLAY, self::PAUSE, self::LIMIT];

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
