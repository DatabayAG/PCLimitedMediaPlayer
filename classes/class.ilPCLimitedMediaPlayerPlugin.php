<?php
/**
 * Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

/**
 * Page Component Limited Media Player plugin
 */
class ilPCLimitedMediaPlayerPlugin extends ilPageComponentPlugin
{
    const DEBUG = false;

	function getDebug()
    {
        return self::DEBUG;
    }

	function isValidParentType(string $a_type): bool
	{
		return in_array($a_type, ['qpl']);
	}

	function getJavascriptFiles(string $a_mode): array
	{
		return ['js/ilPCLimitedMediaPlayerPage.js'];
	}

	function getCssFiles(string $a_mode): array
	{
        return [];
	}

    /**
     * Get the URL of the player script
     */
	public function getPlayerUrl(): string
    {
        return $this->getDirectory() . '/player.php';
    }

}