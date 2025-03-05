<?php
/**
 * Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

use ILIAS\Plugin\LimitedMediaPlayer\ParentType;
use ILIAS\Plugin\LimitedMediaPlayer\Factory;

/**
 * Page Component Limited Media Player plugin
 */
class ilPCLimitedMediaPlayerPlugin extends ilPageComponentPlugin
{
    public const ID = 'limply';

    /**
     * Activate to show debugging info in the page component
     */
    private const DEBUG = false;

    private ?Factory $factory = null;

    public function isValidParentType(string $a_type): bool
	{
		return in_array($a_type, ParentType::CASES);
	}

	public function getJavascriptFiles(string $a_mode): array
	{
		return ['js/ilPCLimitedMediaPlayerPage.js'];
	}

    public function getCssFiles(string $a_mode): array
	{
        return [];
	}

    public function factory(): Factory
    {
        return $this->factory ?? new Factory($this->db);
    }

    public function getDebug(): bool
    {
        return self::DEBUG;
    }

    /**
     * This URL is used for the iframe showing the player
     */
    public function getPlayerUrl(): string
    {
        return $this->getDirectory() . '/player.php';
    }

}