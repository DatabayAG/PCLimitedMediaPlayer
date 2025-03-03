<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

use DOMDocument;
use DOMXPath;
use DOMElement;
use ilDBInterface;

class MediaRepo
{
    private ilDBInterface $db;

    public function __construct(
        ilDBInterface $db
    ) {
        $this->db = $db;
    }

    /**
     * Find the limited media on pages
     *
     * @param   int[]       $a_page_ids     ids of pages to scan
     * @param   string      $a_parent_type  type of pages to scan
     * @param   string      $a_lang         language of pages to scan
     * @param   int[]|null  $a_mob_id       id of a media object to search for
     *
     * @return  Medium[]
     */
    public function findLimitedMedia(array $a_page_ids, string $a_parent_type = 'qpl', string $a_lang = '-', ?int $a_mob_id = null): array
    {
        $query = "SELECT page_id, content FROM page_object "
            . " WHERE parent_type = " . $this->db->quote($a_parent_type, 'text')
            . " AND lang = " . $this->db->quote($a_lang, 'text')
            . " AND " . $this->db->in('page_id', $a_page_ids, false, 'integer')
            . " AND " . $this->db->like('content', 'text', '%PCLimitedMediaPlayer%', false);
        $result = $this->db->query($query);

        $found = [];
        while ($row = $this->db->fetchAssoc($result)) {
            $domdoc = new DOMDocument("1.0", "UTF-8");
            $domdoc->loadXML($row['content']);
            $xpath = new DOMXPath($domdoc);
            $pnodes = $xpath->query("//Plugged[@PluginName='PCLimitedMediaPlayer']");

            /** @var DOMElement $cnode */
            foreach ($pnodes as $pnode) {
                $properties = array();
                /** @var DOMElement $child */
                foreach ($pnode->childNodes as $child) {
                    $properties[$child->getAttribute('Name')] = $child->nodeValue;
                }

                $mpcid = $properties['medium_pcid'] ?? '';
                $mnodes = $xpath->query("//PageContent[@PCID='$mpcid']/MediaObject/MediaAlias");
                $mnode = $mnodes->item(0);
                if ($mnode !== null) {
                    $origin = $mnode->getAttribute('OriginId');
                    $parts = explode('_', $origin);
                    $mob_id = (int) end($parts);

                    if ($a_mob_id === null || $mob_id == $a_mob_id) {
                        $found[] = new Medium(
                            (int) $row['page_id'] ?? 0,
                            $mob_id,
                            (string)  $properties['medium_title'] ?? '',
                            (int) $properties['limit_plays'] ?? 0
                        );
                    }
                }
            }
        }

        return $found;
    }
}
