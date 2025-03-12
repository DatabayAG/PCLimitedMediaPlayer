<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

use ilPCLimitedMediaPlayerPlugin;
use ilWACSignedPath;
use ilUtil;
use ilGlobalTemplateInterface;
use ILIAS\HTTP\GlobalHttpState;
use ILIAS\Filesystem\Stream\Streams;

/**
 * Class to show the player and handle updates
 * This is called from an iframe embedding the player
 */
class Player
{
    private GlobalHttpState $http;
    private RequestVariables $get;
    private RequestVariables $post;

    private ilPCLimitedMediaPlayerPlugin $plugin;
    private UsageRepo $usage_repo;
    private PreferencesRepo $preferences_repo;

    /**
     * @var string  Path to the mediaelement player
     */
    private $mejs_path = "lib/mediaelement-4.1.3";

    /**
     * Parameters stored with the limited media object, added to the request
     */
    private int $parent_id;
    private int $page_id;
    private int $mob_id;
    private string $file;
    private string $mime;
    private string $startpic;
    private int $height;
    private int $width;
    private string $limit_context;
    private int $limit_plays;
    private bool $play_pause;

    /**
     * Internal status variables
     */
    private Usage $usage;
    private Status $status;
    private int $current_plays = 0;
    private ?float $current_seconds = null;
    private float $volume;

    public function __construct()
    {
        global $DIC;

        $this->plugin = $DIC["component.factory"]->getPlugin(ilPCLimitedMediaPlayerPlugin::ID);
        $this->get = new RequestVariables($DIC->http()->wrapper()->query(), $DIC->refinery());
        $this->post = new RequestVariables($DIC->http()->wrapper()->post(), $DIC->refinery());

        $this->parent_id = (int) $this->get->integer('parent_id');
        $this->page_id = (int) $this->get->integer('page_id');
        $this->mob_id = (int) $this->get->integer('mob_id');
        $this->file = (string) $this->get->string('file');
        $this->mime = (string) $this->get->string('mime');
        $this->startpic = (string) $this->get->string('startpic');
        $this->height = (int) $this->get->integer('height');
        $this->width = (int) $this->get->integer('width');
        $this->limit_context = (string) $this->get->string('limit_context');
        $this->limit_plays = (int) $this->get->integer('limit_plays');
        $this->play_pause = (bool) $this->get->bool('play_pause');

        $this->usage_repo = $this->plugin->factory()->usageRepo($this->parent_id, $this->page_id, $this->mob_id, LimitContext::from($this->limit_context));
        $this->preferences_repo = $this->plugin->factory()->preferencesRepo();

        $this->usage = $this->usage_repo->get($DIC->user()->getId());

    }


    /**
     * Handle the player request
     * The player is called from an iframe of the media object
     * ilCtrl is not used
     */
    public function handleRequest()
    {
        switch ($this->get->string('cmd')) {
            case 'show':
                $this->usage->setPageView($this->play_pause);
                $this->current_plays = (int) $this->usage->getPlays();
                $this->current_seconds = (int) $this->usage->getSeconds();
                $this->status = $this->usage->getStatus($this->limit_plays, $this->play_pause);
                $this->volume = $this->preferences_repo->getVolume();
                // show a page with the embedded player
                $this->showPlayer();
                break;

            case 'update':
                // update the usage data (ajax call)
                $this->updateUsage();
                break;

            case 'volume':
                // update the volume setting (ajax call)
                $this->updateVolume();
                break;

            default:
                echo 'unsupported';
        }
    }

    /**
     * Show a page with embedded player
     * The page is called from an iframe, so it only shows the player and the counters
     */
    protected function showPlayer()
    {
        $medium_path = './data/' . CLIENT_ID . '/mobs/mm_' . $this->mob_id . '/' . $this->file;
        if (class_exists('ilWACSignedPath')) {
            $medium_path = ilWACSignedPath::signFile($medium_path);
        }
        $medium_path = LIMPLY_BACKSTEPS . $medium_path;


        if ($this->startpic) {
            $startpic_path = './data/' . CLIENT_ID . '/mobs/mm_' . $this->mob_id . '/' . $this->startpic;
            if (class_exists('ilWACSignedPath')) {
                $startpic_path = ilWACSignedPath::signFile($startpic_path);
            }
            $startpic_path = LIMPLY_BACKSTEPS . $startpic_path;
        } else {
            $startpic_path = LIMPLY_BACKSTEPS . ilUtil::getImagePath('mcst_preview.svg');
        }

        /** @var ilGLobalTemplateInterface $tpl */
        $tpl = $this->plugin->getTemplate("tpl.player.html");

        $tpl->setCurrentBlock('startpic');
        $tpl->setVariable("FILE", $startpic_path);
        $tpl->setVariable("HEIGHT", $this->height);
        $tpl->setVariable("WIDTH", $this->width);
        $tpl->parseCurrentBlock();

        // show only startpic if limit is reached
        if ($this->status->value() == Status::LIMIT) {
            $tpl->printToStdout();
            return;
        }

        $update_url = "player.php?cmd=update"
            . "&limit_plays=" . $this->limit_plays
            . "&limit_context=" . $this->limit_context
            . "&parent_id=" . $this->parent_id
            . "&page_id=" . $this->page_id
            . "&mob_id=" . $this->mob_id;

        $volume_url = "player.php?cmd=volume";

        $config = array(
            'type' => substr($this->mime, 0, 5) == 'audio' ? 'audio' : 'video',
            'mob_id' => $this->mob_id,
            'play_pause' => $this->play_pause,
            'current_plays' => $this->current_plays,
            'current_seconds' => $this->current_seconds,
            'status' => $this->status,
            'volume' => $this->volume,
            'update_url' => $update_url,
            'volume_url' => $volume_url,
        );

        $tpl->setCurrentBlock($config['type']);
        $tpl->setVariable("FILE", $medium_path);
        $tpl->setVariable("WIDTH", $this->width);
        $tpl->setVariable("HEIGHT", $this->height);
        $tpl->setVariable("MIME", $this->mime);
        $tpl->parseCurrentBlock();

        $js_files =  \ilPlayerUtil::getJsFilePaths();

        $tpl->setVariable("JQUERY_URL", $this->mejs_path . '/build/jquery.js');
        $tpl->setVariable("PLAYER_JS_URL", $this->mejs_path . '/build/mediaelement-and-player.js');
        $tpl->setVariable("PLAYER_CSS_URL", $this->mejs_path . '/build/mediaelementplayer.css');
        $tpl->setVariable("FRAME_JS_URL", "js/ilPCLimitedMediaPlayerFrame.js");
        $tpl->setVariable("CONFIG", json_encode($config));

        $this->http->saveResponse($this->http->response()->withBody(Streams::ofString($tpl->printToString())));
        $this->http->sendResponse();
        $this->http->close();
    }

    /**
     * Update the usage data of the currently played medium (called by ajax)
     */
    protected function updateUsage()
    {
        $plays = $this->post->integer('current_plays');
        $seconds = $this->post->float('current_seconds');

        $this->usage->setProgress($plays ?? 0, $seconds ?? null);
        $this->usage_repo->save($this->usage);

        $this->http->saveResponse($this->http->response()->withBody(Streams::ofString(json_encode([
                'status' => (string) $this->usage->getStatus($this->limit_plays, false),
                'seconds' => (float) $this->usage->getSeconds(),
                'plays' => (int) $this->usage->getPlays()
            ]))));
        $this->http->sendResponse();
        $this->http->close();
    }

    /**
     * Update the stored player volume (called by ajax)
     */
    protected function updateVolume()
    {
        $this->preferences_repo->updateVolume($this->post->float('volume') ?? 0.5);

        $this->http->saveResponse($this->http->response()->withBody(Streams::ofString(json_encode(true))));
        $this->http->sendResponse();
        $this->http->close();
    }
}
