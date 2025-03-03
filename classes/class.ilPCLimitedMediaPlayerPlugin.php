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

    /**
     * Find the limited media on pages
     * @param   int[]       $a_page_ids     ids of pages to scan
     * @param   string      $a_parent_type  type of pages to scan
     * @param   string      $a_lang         language of pages to scan
     * @param   int[]|null  $a_mob_id       id of a media object to search for
     *
     * @return  array   [['page_id' => int, 'mob_id' => int, 'title' => string, 'limit' => int], ...]
     */
    public static function findLimitedMedia($a_page_ids, $a_parent_type = 'qpl', $a_lang = '-', $a_mob_id = null)
    {
        global $ilDB;

        $query = "SELECT page_id, content FROM page_object "
            ." WHERE parent_type = ". $ilDB->quote($a_parent_type, 'text')
            ." AND lang = ". $ilDB->quote($a_lang, 'text')
            ." AND ". $ilDB->in('page_id', $a_page_ids, false, 'integer')
            ." AND " . $ilDB->like('content', 'text', '%PCLimitedMediaPlayer%', false);
        $result = $ilDB->query($query);

        $found = array();
        while ($row = $ilDB->fetchAssoc($result))
        {
            $domdoc = new DOMDocument("1.0", "UTF-8");
            $domdoc->loadXML($row['content']);
            $xpath = new DOMXPath($domdoc);
            $pnodes = $xpath->query("//Plugged[@PluginName='PCLimitedMediaPlayer']");

            /** @var DOMElement $cnode */
            foreach($pnodes as $pnode)
            {
                $properties = array();
                /** @var DOMElement $child */
                foreach($pnode->childNodes as $child)
                {
                    $properties[$child->getAttribute('Name')] = $child->nodeValue;
                }

                $mpcid = $properties['medium_pcid'];
                $mnodes = $xpath->query("//PageContent[@PCID='$mpcid']/MediaObject/MediaAlias");
                $mnode = $mnodes->item(0);
                if (isset($mnode))
                {
                    $origin = $mnode->getAttribute('OriginId');
                    $parts = explode('_', $origin);
                    $mob_id = (int) end($parts);

                    if (!isset($a_mob_id) || $mob_id == $a_mob_id)
                    {
                        $found[] = array(
                            'page_id' => $row['page_id'],
                            'mob_id' => $mob_id,
                            'title' => $properties['medium_title'],
                            'limit' => $properties['limit_plays']
                        );
                    }
                }
            }
        }

        return $found;
    }
}