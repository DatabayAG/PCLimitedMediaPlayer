<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

use DOMDocument;
use DOMXPath;
use DOMElement;
use ilDBInterface;
use ILIAS\ResourceStorage\Services as ResourceStorage;
use ILIAS\ResourceStorage\Stakeholder\ResourceStakeholder;
use ILIAS\ResourceStorage\Stakeholder\Repository\StakeholderRepository;
use ILIAS\ResourceStorage\Stakeholder\Repository\StakeholderDBRepository;

class MediumRepo
{
    private ilDBInterface $db;
    private ResourceStorage $storage;
    private StakeholderRepository $stakeholder_repo;
    private ResourceStakeholder $upload_stakeholder;
    private ResourceStakeholder $use_stakeholder;

    public function __construct(
        ilDBInterface $db,
        ResourceStorage $storage,
        StakeholderRepository $stakeholder_repo,
        ResourceStakeholder $upload_stakeholder,
        ResourceStakeholder $use_stakeholder,
    ) {
        $this->db = $db;
        $this->storage = $storage;
        $this->stakeholder_repo = $stakeholder_repo;
        $this->upload_stakeholder = $upload_stakeholder;
        $this->use_stakeholder = $use_stakeholder;
    }


    public function getFileName($file_id): ?string
    {
        $id = $this->storage->manage()->find($file_id);
        if ($id === null) {
            return null;
        }
        return $this->storage->manage()->getCurrentRevision($id)->getTitle();
    }

    public function getMimeType($file_id): ?string
    {
        $id = $this->storage->manage()->find($file_id);
        if ($id === null) {
            return null;
        }
        return $this->storage->manage()->getCurrentRevision($id)->getInformation()->getMimeType();
    }


    public function setFileUsed($file_id): void
    {
        $id = $this->storage->manage()->find($file_id);
        if ($id !== null) {
            $this->stakeholder_repo->deregister($id, $this->upload_stakeholder);
            $this->stakeholder_repo->register($id, $this->use_stakeholder);
        }
    }

    public function cleanupUnusedFiles()
    {
        $table = StakeholderDBRepository::TABLE_NAME;

        $result = $this->db->queryF(
            "SELECT rid FROM $table WHERE stakeholder_id = %s",
            ['text'],
            [$this->upload_stakeholder->getId()]
        );

        while ($row = $this->db->fetchAssoc($result)) {
            $id = $this->storage->manage()->find($row['rid']);
            if ($id !== null) {
                $created = $this->storage->manage()->getCurrentRevision($id)->getInformation()->getCreationDate();
                if ($created->getTimestamp() < time() - 3600) {
                    $this->storage->manage()->remove($id, $this->upload_stakeholder);
                }
            }
        }
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
                            (string) $properties['medium_title'] ?? '',
                            (int) $properties['limit_plays'] ?? 0
                        );
                    }
                }
            }
        }

        return $found;
    }
}
