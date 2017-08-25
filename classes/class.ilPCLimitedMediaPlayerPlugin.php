<?php
/**
 * Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

include_once("./Services/COPage/classes/class.ilPageComponentPlugin.php");
 
/**
 * Page Component Limited Media Player plugin
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 */
class ilPCLimitedMediaPlayerPlugin extends ilPageComponentPlugin
{
    const DEBUG = true;

	/**
	 * Get plugin name 
	 *
	 * @return string
	 */
	function getPluginName()
	{
		return "PCLimitedMediaPlayer";
	}

    /**
     * Get the debugging mode
     * @return bool
     */
	function getDebug()
    {
        return self::DEBUG;
    }

	/**
	 * Get plugin name 
	 *
	 * @return string
	 */
	function isValidParentType($a_parent_type)
	{
		if (in_array($a_parent_type, array('qpl')))
		{
			return true;
		}
		return false;
	}
	
	/**
	 * Get Javascript files
	 */
	function getJavascriptFiles($a_mode = null)
	{
		return array('js/ilPCLimitedMediaPlayerPage.js');
	}
	
	/**
	 * Get css files
	 */
	function getCssFiles($a_mode = null)
	{
        return array();
	}


    /**
     * Get the URL for the player script
     * @return string
     */
	public function getPlayerUrl()
    {
        return $this->getDirectory().'/player.php';
    }

    /**
     * Get the limited media on a page
     * @param   array   $a_page_id
     * @return  array   [['mob_id' => int, 'title' => string, 'limit' => int], ...]
     */
    public function getLimitedMedia($a_page_id, $a_mob_id = 0)
    {
        // todo: implement xml analysis
        return array();
    }
}