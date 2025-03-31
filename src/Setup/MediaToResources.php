<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer\Setup;

use DOMDocument;
use DOMXPath;
use DOMElement;
use ilPageObject;
use ilAssQuestionPage;
use ilPCPlugged;
use ilPCMediaObject;
use ilMediaItem;
use ilDBInterface;
use ilPCLimitedMediaPlayerPlugin;
use ILIAS\Plugin\LimitedMediaPlayer\MediumRepo;
use Exception;

class MediaToResources
{
    private ilDBInterface $db;
    private MediumRepo $medium_repo;

    public function __construct()
    {
        global $DIC;

        if (!$DIC->offsetExists("filesystem")) {
            throw new Exception('Please update the ilPCLimitedMediaPlayerPlugin plugin in the ILIAS Administration.');
        }

        $this->db = $DIC->database();
        $this->fs = $DIC->filesystem()->web();
        $this->plugin = $DIC["component.factory"]->getPlugin(ilPCLimitedMediaPlayerPlugin::ID);
        $this->medium_repo = $this->plugin->factory()->mediumRepo();
    }

    public function execute(): void
    {
        $query = "SELECT page_id, content FROM page_object "
            . " WHERE " . $this->db->like('content', 'text', '%PCLimitedMediaPlayer%', false);
        $result = $this->db->query($query);

        while ($row = $this->db->fetchAssoc($result)) {
            $dom = new DOMDocument("1.0", "UTF-8");
            $dom->loadXML($row['content']);
            $xpath = new DOMXPath($dom);
            $player_nodes = $xpath->query("//Plugged[@PluginName='PCLimitedMediaPlayer']/parent::PageContent");

            if (!empty($player_nodes)) {
                $page = new ilAssQuestionPage((int) $row['page_id']);
                $page->buildDom();

                /** @var DOMElement $player_node */
                foreach ($player_nodes as $player_node) {
                    $file_name = '';
                    $preview_name = '';

                    /** @var ilPCPlugged $player_content */
                    $player_pcid = $player_node->getAttribute('PCID');
                    $player_content = $page->getContentObjectForPcId($player_pcid);
                    if ($player_content !== null) {
                        $properties = $player_content->getProperties();

                        $medium_pcid = $properties['medium_pcid'] ?? '';
                        /** @var ilPCMediaObject $medium_content */
                        if ($medium_pcid !== '') {
                            $medium_content = $page->getContentObjectForPcId($medium_pcid);
                            if ($medium_content !== null) {
                                $media_object = $medium_content->getMediaObject();
                                if ($media_object !== null) {
                                    /** @var ilMediaItem $item */
                                    $item = $media_object->getMediaItem('Fullscreen');
                                    if ($item !== null) {
                                        $file = $item->getLocation();
                                        $preview = $media_object->getVideoPreviewPic(true);

                                        $file_id = $this->migrateMobFile($media_object->getId(), $file);
                                        $preview_id = $this->migrateMobFile($media_object->getId(), $preview);

                                        $page->deleteContent('', false, $medium_content->getPCId());
                                        $player_content->setProperties([
                                            'file_id' => $file_id,
                                            'preview_id' => $preview_id,
                                            'title' => $properties['medium_title'] ?? '',
                                            'limit_plays' => $properties['limit_plays'] ?? '',
                                            'limit_context' => $properties['limit_context'] ?? '',
                                            'width' => $properties['medium_width'] ?? '',
                                            'height' => $properties['medium_height'] ?? '',
                                            'play_in_modal' =>  $properties['play_modal'] ?? '',
                                            'play_with_pause' =>  $properties['play_pause'] ?? ''
                                        ]);

                                        $page->update();
                                        $media_object->delete();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    protected function migrateMobFile(int $mob_id, string $filename): string
    {
        if ($filename == '') {
            return '';
        }
        $path = '/mobs/mm_' . $mob_id . '/' . $filename;
        if ($this->fs->has($path)) {
            $stream = $this->fs->readStream($path);
            return $this->medium_repo->addFileFromStream($stream, $filename);
        }
        return '';
    }
}
