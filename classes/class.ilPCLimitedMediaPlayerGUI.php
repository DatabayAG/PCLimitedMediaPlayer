<?php

declare(strict_types=1);

use ILIAS\HTTP\GlobalHttpState;
use ILIAS\Filesystem\Stream\Streams;
use ILIAS\Plugin\LimitedMediaPlayer\Status;
use ILIAS\Plugin\LimitedMediaPlayer\Usage;
use ILIAS\Plugin\LimitedMediaPlayer\RequestVariables;
use ILIAS\Plugin\LimitedMediaPlayer\LimitContext;
use ILIAS\Plugin\LimitedMediaPlayer\UsageRepo;
use ILIAS\Plugin\LimitedMediaPlayer\PreferencesRepo;
use ILIAS\Plugin\LimitedMediaPlayer\MediumRepo;
use ILIAS\Plugin\LimitedMediaPlayer\MediumDelivery;

/**
 * GUI class to show the player and handle updates (called from iframe)
 *
 * @ilCtrl_isCalledBy ilPCLimitedMediaPlayerGUI: ilUIPluginRouterGUI
 */
class ilPCLimitedMediaPlayerGUI
{
    private const SHOW_PLAYER = 'showPlayer';
    private const CMD_UPDATE_USAGE = 'updateUsage';
    private const CMD_UPDATE_VOLUME = 'updateVolume';

    private ilCtrlInterface $ctrl;
    private GlobalHttpState $http;

    private ilPCLimitedMediaPlayerPlugin $plugin;
    private MediumRepo $medium_repo;
    private UsageRepo $usage_repo;
    private PreferencesRepo $preferences_repo;
    private RequestVariables $get;
    private RequestVariables $post;
    private MediumDelivery $delivery;

    /**
     * Parameters stored with the limited media object, added to the request
     */
    private int $parent_id;
    private int $page_id;
    private string $file_id;
    private string $preview_id;
    private int $height;
    private int $width;
    private LimitContext $limit_context;
    private int $limit_plays;
    private bool $play_with_pause;

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
        $this->ctrl = $DIC->ctrl();
        $this->http = $DIC->http();
        $this->get = $this->plugin->factory()->getVariables();
        $this->post =$this->plugin->factory()->postVariables();

        $this->parent_id = (int) $this->get->integer('parent_id');
        $this->page_id = (int) $this->get->integer('page_id');
        $this->file_id = (string) $this->get->string('file_id');
        $this->preview_id = (string) $this->get->string('preview_id');
        $this->height = (int) $this->get->integer('height');
        $this->width = (int) $this->get->integer('width');
        $this->limit_context = LimitContext::tryFrom((string) $this->get->string('limit_context')) ?? LimitContext::from(LimitContext::TESTPASS);
        $this->limit_plays = (int) $this->get->integer('limit_plays');
        $this->play_with_pause = (bool) $this->get->bool('play_with_pause');

        $this->medium_repo = $this->plugin->factory()->mediumRepo();
        $this->usage_repo = $this->plugin->factory()->usageRepo();
        $this->preferences_repo = $this->plugin->factory()->preferencesRepo();
        $this->usage = $this->usage_repo->get($DIC->user()->getId(), $this->parent_id, $this->page_id, $this->file_id, $this->limit_context);
        $this->volume = $this->preferences_repo->getVolume();
        $this->delivery = $this->plugin->factory()->mediumDelivery();

    }

    public function executeCommand(): void
    {
        switch ($cmd = $this->ctrl->getCmd(self::SHOW_PLAYER)) {
            case self::SHOW_PLAYER:
            case self::CMD_UPDATE_USAGE:
            case self::CMD_UPDATE_VOLUME:
                $this->$cmd();
                break;

            default:
                echo 'unsupported command';
        }
    }

    /**
     * Show a page with embedded player
     * The page is called from an iframe, so it only shows the player and the counters
     */
    private function showPlayer()
    {
        // notify the page view and adapt status
        $this->usage->setPageView($this->play_with_pause);
        $this->current_plays = (int) $this->usage->getPlays();
        $this->current_seconds = (int) $this->usage->getSeconds();
        $this->status = $this->usage->getStatus($this->limit_plays, $this->play_with_pause);

        $mime_type = $this->medium_repo->getMimeType($this->file_id) ?? '';
        $file_url = $this->delivery->getUrl($this->file_id) ?? '';
        $preview_url = $this->delivery->getUrl($this->preview_id) ?? '';

        $tpl = $this->plugin->getTemplate("tpl.player.html");

        $tpl->setCurrentBlock('preview');
        $tpl->setVariable("FILE", $preview_url);
        $tpl->setVariable("HEIGHT", $this->height);
        $tpl->setVariable("WIDTH", $this->width);
        $tpl->parseCurrentBlock();

        //show only preview if limit is reached
        if ($this->status->value() == Status::LIMIT) {
            $this->http->saveResponse($this->http->response()->withBody(Streams::ofString($tpl->get())));
            $this->http->sendResponse();
            $this->http->close();
        }

        $this->ctrl->setParameter($this, 'limit_plays', $this->limit_plays);
        $this->ctrl->setParameter($this, 'limit_context', $this->limit_context->value());
        $this->ctrl->setParameter($this, 'parent_id', $this->parent_id);
        $this->ctrl->setParameter($this, 'page_id', $this->page_id);
        $this->ctrl->setParameter($this, 'parent_id', $this->parent_id);
        $this->ctrl->setParameter($this, 'file_id', $this->file_id);

        $update_url = $this->ctrl->getLinkTarget($this, 'updateUsage');
        $volume_url = $this->ctrl->getLinkTarget($this, 'updateVolume');

        $config = array(
            'type' => substr($mime_type, 0, 5) == 'audio' ? 'audio' : 'video',
            'file_id' => $this->file_id,
            'play_with_pause' => $this->play_with_pause,
            'current_plays' => $this->current_plays,
            'current_seconds' => $this->current_seconds,
            'status' => $this->status,
            'volume' => $this->volume,
            'update_url' => $update_url,
            'volume_url' => $volume_url,
        );

        $tpl->setCurrentBlock($config['type']);
        $tpl->setVariable("FILE", $file_url);
        $tpl->setVariable("WIDTH", $this->width);
        $tpl->setVariable("HEIGHT", $this->height);
        $tpl->setVariable("MIME", $mime_type);
        $tpl->parseCurrentBlock();

        $scripts = [iljQueryUtil::getLocaljQueryPath()];
        $scripts = array_merge($scripts, ilPlayerUtil::getLocalMediaElementJsPath());
        $scripts[] = $this->plugin->getDirectory() . '/resources/limited_media_player_frame.js';

        foreach ($scripts as $script) {
            $tpl->setCurrentBlock('script');
            $tpl->setVariable("SCRIPT_URL", $script);
            $tpl->parseCurrentBlock();
        }

        $styles = [ilPlayerUtil::getLocalMediaElementCssPath()];
        $styles[] = $this->plugin->getDirectory() . '/resources/limited_media_player_style.css';
        foreach ($styles as $style) {
            $tpl->setCurrentBlock('style');
            $tpl->setVariable("STYLE_URL", $style);
            $tpl->parseCurrentBlock();
        }

        $tpl->setVariable("CONFIG", json_encode($config));

        $this->http->saveResponse($this->http->response()->withBody(Streams::ofString($tpl->get())));
        $this->http->sendResponse();
        $this->http->close();
    }

    /**
     * Update the usage data of the currently played medium (called by ajax)
     */
    private function updateUsage()
    {
        $plays = $this->post->integer('current_plays');
        $seconds = $this->post->float('current_seconds');

        $this->usage->setProgress($plays ?? 0, $seconds ?? null);
        $this->usage_repo->save($this->usage, $this->limit_context);

        $this->http->saveResponse($this->http->response()->withBody(Streams::ofString(json_encode([
                'status' => (string) $this->usage->getStatus($this->limit_plays, false)->value(),
                'seconds' => (float) $this->usage->getSeconds(),
                'plays' => (int) $this->usage->getPlays()
            ]))));
        $this->http->sendResponse();
        $this->http->close();
    }

    /**
     * Update the stored player volume (called by ajax)
     */
    private function updateVolume()
    {
        $this->preferences_repo->updateVolume($this->post->float('volume') ?? 0.5);

        $this->http->saveResponse($this->http->response()->withBody(Streams::ofString(json_encode(true))));
        $this->http->sendResponse();
        $this->http->close();
    }
}
