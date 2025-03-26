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
use ilDBConstants;
use ILIAS\Filesystem\Stream\FileStream;

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

    public function getFileStream(string $file_id): ?FileStream
    {
        $id = $this->storage->manage()->find($file_id);
        if ($id === null) {
            return null;
        }
        return $this->storage->consume()->stream($id)->getStream();
    }

    public function getFileName(string $file_id): ?string
    {
        $id = $this->storage->manage()->find($file_id);
        if ($id === null) {
            return null;
        }
        return $this->storage->manage()->getCurrentRevision($id)->getTitle();
    }

    public function getMimeType(?string $file_id): ?string
    {
        $id = $this->storage->manage()->find($file_id);
        if ($id === null) {
            return null;
        }
        return $this->storage->manage()->getCurrentRevision($id)->getInformation()->getMimeType();
    }

    public function setFileUsed(string $file_id): void
    {
        $id = $this->storage->manage()->find($file_id);
        if ($id !== null) {
            $this->stakeholder_repo->deregister($id, $this->upload_stakeholder);
            $this->stakeholder_repo->register($id, $this->use_stakeholder);
        }
    }

    public function removeFileUsage(string $file_id, int $page_id): void
    {
        if (!$this->isFileOnOtherPages($file_id, $page_id)) {
            $id = $this->storage->manage()->find($file_id);
            if ($id !== null) {
                $this->storage->manage()->remove($id, $this->use_stakeholder);
            }
        }
    }

    public function cloneFile(string $file_id): ?string
    {
        $id = $this->storage->manage()->find($file_id);
        if ($id !== null) {
            $new_id = $this->storage->manage()->clone($id);
            return (string) $new_id;
        }
        return '';
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

    private function isFileOnOtherPages(string $file_id, int $page_id): bool
    {
        $query = "SELECT page_id FROM page_object WHERE parent_type = 'qpl' "
            . " AND page_id <> " . $this->db->quote($page_id, ilDBConstants::T_INTEGER)
            . " AND " . $this->db->like('content', ilDBConstants::T_TEXT, "%$file_id%", false)
            . " LIMIT 1";
        $result = $this->db->query($query);

        if ($row = $this->db->fetchAssoc($result)) {
            return true;
        }
        return false;
    }

    /**
     * Get the medium on a page
     */
    public function getMedium(?int $page_id, ?string $file_id): ?Medium
    {
        if ($page_id !== null && $file_id !== null) {
            foreach ($this->findMedia((array) $page_id, $file_id) as $medium) {
                return $medium;
            }
        }
        return null;
    }

    /**
     * Find the limited media on pages
     *
     * @param   int[]       $a_page_ids     ids of pages to scan
     * @param   string      $a_parent_type  type of pages to scan
     * @param   string      $a_lang         language of pages to scan
     * @param   int[]|null  $a_mob_id       id of a media object to search for
     *
     * @return  array<string, Medium>   pageId_fileId => Medium
     */
    public function findMedia(array $a_page_ids, ?string $file_id = null): array
    {
        $query = "SELECT page_id, content FROM page_object "
            . " WHERE " . $this->db->in('page_id', $a_page_ids, false, 'integer')
            . " AND " . $this->db->like('content', 'text', '%PCLimitedMediaPlayer%', false);
        $result = $this->db->query($query);

        $found = [];
        while ($row = $this->db->fetchAssoc($result)) {
            $dom = new DOMDocument("1.0", "UTF-8");
            $dom->loadXML($row['content']);
            $xpath = new DOMXPath($dom);
            $nodes = $xpath->query("//Plugged[@PluginName='PCLimitedMediaPlayer']");

            /** @var DOMElement $node */
            foreach ($nodes as $node) {
                $properties = array();
                /** @var DOMElement $child */
                foreach ($node->childNodes as $child) {
                    $properties[$child->getAttribute('Name')] = $child->nodeValue;
                }
                if ($file_id === null || $file_id == $properties['file_id'] ?? '') {
                    $medium = Medium::fromProperties((int) $row['page_id'], $properties);
                    $found[$medium->getKey()] = $medium;
                }
            }
        }

        return $found;
    }
}
