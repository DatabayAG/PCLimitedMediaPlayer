<?php

declare(strict_types=1);

use ILIAS\Plugin\LimitedMediaPlayer\LimitContext;
use ILIAS\Plugin\LimitedMediaPlayer\Status;
use ILIAS\Plugin\LimitedMediaPlayer\Usage;
use ILIAS\Plugin\LimitedMediaPlayer\Limit;
use ILIAS\Plugin\LimitedMediaPlayer\StakeholderForUpload;
use ilGlobalTemplateInterface as Gti;
use ILIAS\UI\Factory as UiFactory;
use ILIAS\UI\Renderer as UiRenderer;
use ILIAS\UI\Component\Input\Container\Form\Standard as Form;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\Plugin\LimitedMediaPlayer\Medium;
use Psr\Http\Message\RequestInterface;
use ILIAS\Plugin\LimitedMediaPlayer\RequestVariables;
use ILIAS\Plugin\LimitedMediaPlayer\MediumRepo;
use ILIAS\Plugin\LimitedMediaPlayer\LimitRepo;
use ILIAS\Plugin\LimitedMediaPlayer\UsageRepo;
use ILIAS\HTTP\GlobalHttpState;

/**
 * @ilCtrl_isCalledBy ilPCLimitedMediaPlayerPluginGUI: ilPCPluggedGUI
 * @ilCtrl_isCalledBy ilPCLimitedMediaPlayerPluginGUI: ilUIPluginRouterGUI
 */
class ilPCLimitedMediaPlayerPluginGUI extends ilPageComponentPluginGUI
{
    public const VIEW_PRESENTATION = 'presentation';
    public const VIEW_PREVIEW = 'preview';
    public const VIEW_META = 'meta';

    public const CMD_CREATE_PLUG = 'create_plug'; // needed for the page editor this way
    public const CMD_CREATE = 'create';
    public const CMD_EDIT = 'edit';
    public const CMD_UPDATE = 'update';
    public const CMD_CANCEL = 'cancel';

    /** @var ilPCLimitedMediaPlayerPlugin $plugin */
    protected ilPageComponentPlugin $plugin;
    private ?ilPageContent $content_object;
    private ilPageObject $page_obj;
    private ilPCMediaObject $page_media_object;

    private ilCtrlInterface $ctrl;
    private GlobalHttpState $http;
    private ilGlobalTemplateInterface $tpl;

    private ilTabsGUI $tabs;
    private ilObjUser $user;
    private UiFactory $ui_factory;
    private UiRenderer $ui_renderer;
    private Refinery $refinery;
    private ilCtrlAwareStorageUploadHandler $upload_handler;
    private RequestInterface $request;

    private MediumRepo $medium_repo;
    private LimitRepo $limit_repo;
    private UsageRepo $usage_repo;
    private RequestVariables $get;
    private string $error_message;

    public function __construct()
    {
        global $DIC;

        parent::__construct();

        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->tabs = $DIC->tabs();
        $this->user = $DIC->user();
        $this->ui_factory = $DIC->ui()->factory();
        $this->ui_renderer = $DIC->ui()->renderer();
        $this->refinery = $DIC->refinery();
        $this->request = $DIC->http()->request();

        $this->plugin = $DIC["component.factory"]->getPlugin(ilPCLimitedMediaPlayerPlugin::ID);
        $this->medium_repo = $this->plugin->factory()->mediumRepo();
        $this->limit_repo = $this->plugin->factory()->limitRepo();
        $this->usage_repo = $this->plugin->factory()->usageRepo();
        $this->upload_handler = $this->plugin->factory()->uploadHandler();
        $this->get = $this->plugin->factory()->getVariables();
    }

    public function executeCommand(): void
    {
        switch ($class = $this->ctrl->getCmdClass()) {
            case strtolower(ilPCLimitedMediaPlayerUploadHandlerGUI::class):
                $this->ctrl->forwardCommand($this->upload_handler);
                break;

            default:
                switch ($cmd = $this->ctrl->getCmd()) {
                    case self::CMD_CREATE_PLUG:
                    case self::CMD_CREATE:
                        $this->create();
                        break;

                    case self::CMD_EDIT:
                    case self::CMD_UPDATE:
                    case self::CMD_CANCEL:
                        $this->$cmd();
                        break;

                    default:
                        $this->tpl->setContent('unknown command');
                }
        }
    }

    /**
     * Show the form to create a new element (directly called)
     */
    public function insert(): void
    {
        $form = $this->initForm(true);
        $this->tpl->setContent($this->ui_renderer->render($form));
    }

    /**
     * Save the new element
     */
    public function create(): void
    {
        $form = $this->initForm(true)->withRequest($this->request);
        if (!empty($data = $form->getData())) {
            if ($this->saveForm($data, true)) {
                $this->tpl->setOnScreenMessage(Gti::MESSAGE_TYPE_SUCCESS, $this->lng->txt("msg_obj_created"), true);
            } else {
                $this->tpl->setOnScreenMessage(Gti::MESSAGE_TYPE_FAILURE, $this->error_message, true);
            }
            $this->returnToParent();
        }
        $this->tpl->setContent($this->ui_renderer->render($form));
    }

    /**
     * Show the edit form
     */
    public function edit(): void
    {
        $this->setTabs("edit");
        $form = $this->initForm();
        $this->tpl->setContent($this->ui_renderer->render($form));
    }

    /**
     * Update the edited element
     */
    private function update(): void
    {
        $form = $this->initForm(false)->withRequest($this->request);
        if (!empty($data = $form->getData())) {
            if ($this->saveForm($data, false)) {
                $this->tpl->setOnScreenMessage(Gti::MESSAGE_TYPE_SUCCESS, $this->lng->txt("msg_obj_modified"), true);
            } else {
                $this->tpl->setOnScreenMessage(Gti::MESSAGE_TYPE_FAILURE, $this->error_message, true);
            }
            $this->returnToParent();
        }
        $this->tpl->setContent($this->ui_renderer->render($form));
    }

    /**
     * Save the posted properties
     */
    private function saveForm(array $data, bool $create): bool
    {
        $previous = $medium = Medium::fromProperties($this->plugin->getPageId(), $this->getProperties());

        $medium = (clone $previous)
            ->setMediumTitle($data['general']['title'] ?? 'Medium')
            ->setFileId($data['general']['file_id'][0] ?? '')
            ->setLimitPlays($data['general']['limit_plays'] ?? null)
            ->setPreviewId($data['details']['preview_id'][0] ?? null)
            ->setWidth($data['details']['width'] ?? null)
            ->setHeight($data['details']['height'] ?? null)
            ->setPlayInModal($data['details']['play_in_modal'] ?? false)
            ->setPlayWithPause($data['details']['play_with_pause'] ?? false);

        if (empty($medium->getFileId())) {
            $this->error_message = $this->plugin->txt('err_missing_file');
            return false;
        }

        if ($create) {
            $success = $this->createElement($medium->toProperties());
        } else {
            $success = $this->updateElement($medium->toProperties());
        }

        if ($success) {
            if ($medium->getFileId() !== $previous->getFileId()) {
                if (!empty($medium->getFileId())) {
                    $this->medium_repo->setFileUsed($medium->getFileId());
                }
                if (!empty($previous->getFileId())) {
                    $this->medium_repo->removeFileUsage($previous->getFileId(), $this->plugin->getPageId());
                }
            }
            if ($medium->getPreviewId() !== $previous->getPreviewId()) {
                if (!empty($medium->getPreviewId())) {
                    $this->medium_repo->setFileUsed($medium->getPreviewId());
                }
                if (!empty($previous->getPreviewId())) {
                    $this->medium_repo->removeFileUsage($previous->getPreviewId(), $this->plugin->getPageId());
                }
            }
        }
        $this->medium_repo->cleanupUnusedFiles();

        if (!$success) {
            $this->error_message = $this->plugin->txt('err_save_properties');
            return false;
        }

        return true;
    }

    /**
     * Init editing form
     */
    private function initForm(bool $create = false): Form
    {
        $medium = Medium::fromProperties($this->plugin->getPageId(), $this->getProperties());

        $factory = $this->ui_factory->input()->field();
        $sections = [];
        $fields = [];

        $fields['title'] = $factory->text($this->plugin->txt('medium_title'))
            ->withRequired(true)
            ->withValue($medium->getTitle());

        $fields['file_id'] = $factory->file($this->upload_handler, $this->plugin->txt('medium_file'))
            ->withValue(empty($medium->getFileId()) ? [] : [$medium->getFileId()])
            ->withRequired(true);

        $fields['limit_plays'] = $factory->numeric($this->plugin->txt('limit_plays'))
            ->withAdditionalTransformation($this->refinery->int()->isGreaterThanOrEqual(0))
            ->withValue($medium->getLimitPlays());

        $fields['limit_context'] = $factory->radio($this->plugin->txt('limit_context'))
            ->withOption(LimitContext::TESTPASS, $this->plugin->txt('limit_context_testpass'))
            ->withOption(LimitContext::SESSION, $this->plugin->txt('limit_context_session'))
            ->withOption(LimitContext::USER, $this->plugin->txt('limit_context_user'))
            ->withValue($medium->getLimitContext()->value());

        $sections['general'] = $factory->section(
            $fields,
            $create ? $this->plugin->txt('cmd_insert') : $this->plugin->txt('edit_limited_media_player')
        );

        $fields = [];

        $fields['preview_id'] = $factory->file($this->upload_handler, $this->plugin->txt('medium_startpic'))
            ->withValue(empty($medium->getPreviewId()) ? [] : [$medium->getPreviewId()]);

        $fields['width'] = $factory->numeric($this->plugin->txt('medium_width'))
            ->withValue($medium->getWidth());

        $fields['height'] = $factory->numeric($this->plugin->txt('medium_height'))
            ->withValue($medium->getHeight());

        $fields['play_in_modal'] = $factory->radio($this->plugin->txt('play_in_modal'))
            ->withOption('0', $this->plugin->txt('play_on_page'), $this->plugin->txt('play_on_page_info'))
            ->withOption('1', $this->plugin->txt('play_in_modal'), $this->plugin->txt('play_in_modal_info'))
            ->withAdditionalTransformation($this->refinery->kindlyTo()->bool())
            ->withValue($medium->getPlayInModal());

        $fields['play_with_pause'] = $factory->radio($this->plugin->txt('play_pause'))
            ->withOption('1', $this->plugin->txt('play_with_pause'), $this->plugin->txt('play_with_pause_info'))
            ->withOption('0', $this->plugin->txt('play_without_pause'), $this->plugin->txt('play_without_pause_info'))
            ->withAdditionalTransformation($this->refinery->kindlyTo()->bool())
            ->withValue($medium->getPlayWithPause());

        $sections['details'] = $factory->section($fields, $this->plugin->txt('settings_details'));

        return $this->ui_factory->input()->container()->form()->standard($this->ctrl->getFormAction(
            $this,
            $create ? self::CMD_CREATE : self::CMD_UPDATE
        ), $sections)
            ->withSubmitLabel($this->lng->txt($create ? 'create' : 'save'));
    }

    public function cancel(): void
    {
        $this->returnToParent();
    }

    public function setTabs($a_active): void
    {
        $this->tabs->addTab("edit", $this->plugin->txt("settings"), $this->ctrl->getLinkTarget($this, "edit"));
        $this->tabs->activateTab($a_active);
    }

    public function getElementHTML(string $a_mode, array $a_properties, string $plugin_version): string
    {
        $info = [];
        $params = [];
        $tpl = $this->plugin->getTemplate("tpl.page_block.html");

        // get the medium and default playing limit
        $medium = Medium::fromProperties($this->plugin->getPageId(), $a_properties);
        $limit = new Limit(0, $this->plugin->getParentId(), $this->plugin->getPageId(), $medium->getFileId(), $this->user->getId(), $medium->getLimitPlays(), true);

        switch ($this->getViewMode()) {
            case self::VIEW_PRESENTATION:
            case self::VIEW_PREVIEW:

                // get the effective playing limit and context
                if ($this->getViewMode() == self::VIEW_PREVIEW) {
                    $limit = $limit->setPlays(null);
                    $limit_context = LimitContext::from(LimitContext::SESSION);
                } else {
                    $limit = $this->limit_repo->effective($limit, $this->plugin->getParentId());
                    $limit_context = $medium->getLimitContext();
                }

                // get the usage and status for the context
                $usage = $this->usage_repo->get($this->user->getId(), $this->plugin->getParentId(), $this->plugin->getPageId(), $medium->getFileId(), $limit_context);
                $usage->setPageView($medium->getPlayWithPause());
                $status = $usage->getStatus($limit->getPlays(), $medium->getPlayWithPause());

                if ($medium->getPlayInModal()) {
                    // show the player and pause/volume in a modal
                    // open the modal by play or continue
                    $html = $this->getElementPlayerHTML($medium, $limit, $limit_context);
                    $controls = ($medium->getPlayWithPause() ? ['pause', 'volume'] : ['volume']);
                    $html .= $this->getElementControlsHTML($medium, $usage, $status, $controls);

                    $modal = ilModalGUI::getInstance();
                    $modal->setId('limplyModal' . $medium->getFileId());
                    $modal->setHeading($medium->getTitle());
                    $modal->setBody($html);
                    $modal->setType(ilModalGUI::TYPE_LARGE);
                    $tpl->setVariable('PLAYER', $modal->getHTML());

                    $controls = ($medium->getPlayWithPause() ? ['play', 'continue'] : ['play']);
                } else {
                    // show the player and all controls embedded
                    $tpl->setVariable('PLAYER', $this->getElementPlayerHTML($medium, $limit, $limit_context));

                    $controls = ($medium->getPlayWithPause() ? ['play', 'pause', 'continue', 'volume'] : ['play', 'volume']);
                }
                $tpl->setVariable('CONTROLS', $this->getElementControlsHTML($medium, $usage, $status, $controls));
                $tpl->setVariable('INFO', $this->getElementInfoHTML($medium, $limit, $usage));

                // prepare javascript
                iljQueryUtil::initjQuery();
                iljQueryUtil::initjQueryUI();
                $tpl->setVariable('MEDIUM_ID', $medium->getFileId());
                $this->tpl->addOnLoadCode('il.PCLimitedMediaPlayerPage.initPage();');
                break;

            case self::VIEW_META:
            default:
                $tpl->setVariable('INFO', $this->getElementInfoHTML($medium, $limit));
        }

        $tpl->setCurrentBlock('title');
        $tpl->setVariable("TITLE", $medium->getTitle());
        $tpl->parseCurrentBlock();
        return $tpl->get();
    }

    protected function getElementPlayerHTML(Medium $medium, Limit $limit, LimitContext $limit_context): string
    {
        $tpl = $this->getPlugin()->getTemplate("tpl.page_player.html");

        $params = [
            'parent_id' => $this->plugin->getParentId(),
            'page_id' => $this->plugin->getPageId(),
            'file_id' => $medium->getFileId(),
            'preview_id' => $medium->getPreviewId(),
            'width' => $medium->getWidth(),
            'height' => max($medium->getHeight(), 50),
            'play_with_pause' => $medium->getPlayWithPause() ? 1 : 0,
            'limit_context' => $limit_context->value(),
            'limit_plays' => $limit->getPlays(),
        ];
        foreach ($params as $name => $value) {
            $this->ctrl->setParameterByClass(ilPCLimitedMediaPlayerGUI::class, $name, $value);
        }
        $url = $this->ctrl->getLinkTargetByClass([ilUIPluginRouterGUI::class, ilPCLimitedMediaPlayerGUI::class]);

        $tpl->setVariable('PLAYER_URL', $url);
        $tpl->setVariable('PLAYER_WIDTH', $medium->getWidth() ?? '100%');
        $tpl->setVariable('PLAYER_HEIGHT', max($medium->getHeight(), 50));

        return $tpl->get();
    }

    /**
     * @param string[] $controls ('play', 'pause', 'continue', 'volume')
     */
    protected function getElementControlsHTML(Medium $medium, Usage $usage, Status $status, array $controls): string
    {
        $preferences_repo = $this->plugin->factory()->preferencesRepo();

        if ($status->value() === Status::LIMIT) {
            return '';
        }

        $tpl = $this->getPlugin()->getTemplate("tpl.page_controls.html");

        if (in_array('play', $controls)) {
            $tpl->setVariable('ID_PLAY', $medium->getFileId());
            $tpl->setVariable('TXT_PLAY', $this->plugin->txt("runtime_play"));
            $tpl->setVariable('STATUS_PLAY', $status->value() == Status::START ? '' : 'hidden');
        }

        if (in_array('pause', $controls)) {
            $tpl->setVariable('ID_PAUSE', $medium->getFileId());
            $tpl->setVariable('TXT_PAUSE', $this->plugin->txt("runtime_pause"));
            $tpl->setVariable('STATUS_PAUSE', $status->value() == Status::PLAY ? '' : 'hidden');
        }

        if (in_array('continue', $controls)) {
            $tpl->setVariable('ID_CONTINUE', $medium->getFileId());
            $tpl->setVariable('TXT_CONTINUE', $this->plugin->txt("runtime_continue"));
            $tpl->setVariable('STATUS_CONTINUE', $status->value() == Status::PAUSE ? '' : 'hidden');
        }

        if (in_array('volume', $controls)) {
            $tpl->setVariable('ID_VOLUME', $medium->getFileId());
            $tpl->setVariable('ICON_VOLUME', ilUtil::getImagePath('icon_mob.svg'));
            $tpl->setVariable('TXT_VOLUME', $this->plugin->txt('runtime_volume'));
            $tpl->setVariable('VALUE_VOLUME', $preferences_repo->getVolume() * 100);
        }

        return $tpl->get();
    }

    /**
     * get the HTML code of the element information
     * @param string[] $info (text => value)
     */
    protected function getElementInfoHTML(Medium $medium, Limit $limit, ?Usage $usage = null): string
    {
        $tpl = $this->getPlugin()->getTemplate("tpl.page_info.html");

        if (isset($usage)) {
            if ($this->getViewMode() == self::VIEW_PREVIEW) {
                $limit_plays_suffix = ', ' . $this->plugin->txt('limit_plays_preview');
            } else {
                $limit_plays_suffix = ', ' . $this->plugin->txt('limit_plays_' . $medium->getLimitContext()->value());

                if (!$limit->isDefault()) {
                    $limit_plays_suffix .= ' ' . $this->plugin->txt('limit_plays_adapted');
                }
            }

            $tpl->setVariable("MAX_PLAYS", ($limit->getPlays() ?? $this->plugin->txt('runtime_no_limit'))
                . $limit_plays_suffix);
            $tpl->setVariable("MAX_PLAYS_TEXT", $this->plugin->txt("runtime_max_plays"));

            $tpl->setVariable("CURRENT_PLAYS", $usage->getPlays());
            $tpl->setVariable("CURRENT_PLAYS_TEXT", $this->plugin->txt("runtime_plays"));

            $tpl->setVariable("CURRENT_SECONDS", max((int) $usage->getSeconds(), 0));
            $tpl->setVariable("CURRENT_SECONDS_TEXT", $this->plugin->txt("runtime_seconds"));
        }

        if ($this->getViewMode() == self::VIEW_META) {

            $info = array(
                $this->plugin->txt('medium_file') => $this->medium_repo->getFileName($medium->getFileId()) ?? $medium->getFileId(),
                $this->plugin->txt('limit_plays') => $medium->getLimitPlays(),
                $this->plugin->txt('limit_context') => $this->plugin->txt('limit_context_' . $medium->getLimitContext()->value()),
                $this->plugin->txt('play_mode') => $this->plugin->txt($medium->getPlayInModal() ? 'play_in_modal' : 'play_on_page'),
                $this->plugin->txt('play_pause') => $medium->getPlayWithPause() ?
                    $this->plugin->txt('play_with_pause') : $this->plugin->txt('play_without_pause')
            );

            // show the static information
            foreach ($info as $text => $value) {
                $tpl->setCurrentBlock('info');
                $tpl->setVariable('INFO_TEXT', $text);
                $tpl->setVariable('INFO_VALUE', $value);
                $tpl->parseCurrentBlock();
            }
        }

        return $tpl->get();
    }

    /**
     * Get how the page content should be presented
     */
    protected function getViewMode(): string
    {
        switch (strtolower($this->get->string('cmdClass'))) {
            case strtolower(ilAssQuestionPreviewGUI::class):
                return self::VIEW_PREVIEW;

            case strtolower(ilObjTestGUI::class):
                if ($this->ctrl->getCmd() == 'preview') {
                    return self::VIEW_PREVIEW;
                } else {
                    return self::VIEW_PRESENTATION;
                }

                // no break
            case strtolower(ilTestPlayerFixedQuestionSetGUI::class):
            case strtolower(ilTestPlayerRandomQuestionSetGUI::class):
                return self::VIEW_PRESENTATION;

            default:
                return self::VIEW_META;
        }
    }
}
