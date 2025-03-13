<?php

declare(strict_types=1);

use ILIAS\Plugin\LimitedMediaPlayer\Factory;

class ilPCLimitedMediaPlayerPlugin extends ilPageComponentPlugin
{
    public const ID = 'limply';
    public const PARENT_TYPE = 'qpl';
    public const DEBUG = false;

    private ?Factory $factory = null;

    public function isValidParentType(string $a_type): bool
    {
        return $a_type == self::PARENT_TYPE;
    }

    public function getJavascriptFiles(string $a_mode): array
    {
        return ['resources/limited_media_player_pages.js'];
    }

    public function getCssFiles(string $a_mode): array
    {
        return [];
    }

    public function factory(): Factory
    {
        return $this->factory ?? new Factory();
    }

}
