<?php

declare(strict_types=1);

use ILIAS\Plugin\LimitedMediaPlayer\LimitContext;
use ILIAS\Plugin\LimitedMediaPlayer\Status;
use ILIAS\Plugin\LimitedMediaPlayer\Usage;
use ILIAS\Plugin\LimitedMediaPlayer\Limit;
use ILIAS\Plugin\LimitedMediaPlayer\Stakeholder;
use ilGlobalTemplateInterface as Gti;
use ILIAS\UI\Factory as UiFactory;
use ILIAS\UI\Renderer as UiRenderer;
use ILIAS\UI\Component\Input\Container\Form\Standard as Form;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\Plugin\LimitedMediaPlayer\Medium;
use Psr\Http\Message\RequestInterface;

/**
 * @ilCtrl_isCalledBy ilPCLimitedMediaPlayerPluginGUI: ilPCPluggedGUI
 * @ilCtrl_calls: ilPCLimitedMediaPlayerPluginGUI: ilCtrlAwareStorageUploadHandler
 */
class ilPCLimitedMediaPlayerPluginGUI extends ilPageComponentPluginGUI
{
    public const VIEW_EDIT = 'edit';
    public const VIEW_OFFLINE = 'offline';
    public const VIEW_PRINT = 'print';
    public const VIEW_PRESENTATION = 'presentation';
    public const VIEW_PREVIEW = 'preview';

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
    private ilGlobalTemplateInterface $tpl;

    private ilTabsGUI $tabs;
    private ilObjUser $user;
    private UiFactory $ui_factory;
    private UiRenderer $ui_renderer;
    private Refinery $refinery;
    private ilCtrlAwareStorageUploadHandler $upload_handler;
    private RequestInterface $request;


    private string $error_message;

    public function __construct()
    {
        global $DIC;

        parent::__construct();

        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC->tpl();
        $this->tabs = $DIC->tabs();
        $this->user = $DIC->user();
        $this->ui_factory = $DIC->ui()->factory();
        $this->ui_renderer = $DIC->ui()->renderer();
        $this->refinery = $DIC->refinery();
        $this->upload_handler = new ilCtrlAwareStorageUploadHandler(new Stakeholder());
        $this->request = $DIC->http()->request();

        $this->plugin = $DIC["component.factory"]->getPlugin(ilPCLimitedMediaPlayerPlugin::ID);
    }

    public function executeCommand(): void
    {
        switch ($class = $this->ctrl->getCmdClass()) {
            case strtolower(ilCtrlAwareStorageUploadHandler::class):
                $this->ctrl->forwardCommand($this->upload_handler);
                break;

            default:
                switch ($cmd = $this->ctrl->getCmd()) {
                    case self::CMD_CREATE:
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
     * Show the creation form
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
    public function update(): void
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
    protected function saveForm(array $data, bool $create): bool
    {
        $medium = Medium::fromProperties($this->getPageId(), $this->getProperties())
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

        if (!$success) {
            $this->error_message = $this->plugin->txt('err_save_properties');
            return false;
        }

        return true;
    }


    /**
     * Init editing form
     */
    protected function initForm(bool $create = false): Form
    {
        $medium = Medium::fromProperties($this->getPageId(), $this->getProperties());

        $factory = $this->ui_factory->input()->field();
        $sections = [];
        $fields = [];

        $fields['title'] = $factory->text($this->plugin->txt('title'))
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
            $create ? $this->plugin->txt('cmd_insert') : $this->lng->txt('edit_limited_media_player')
        );

        $fields = [];

        $fields['preview_id'] = $factory->file($this->upload_handler, $this->plugin->txt('medium_startpic'))
            ->withValue(empty($medium->getPreviewId()) ? [] : [$medium->getPreviewId()]);

        $fields['width'] = $factory->numeric($this->plugin->txt('medium_width'))
            ->withAdditionalTransformation($this->refinery->kindlyTo()->int())
            ->withValue($medium->getWidth());

        $fields['height'] = $factory->numeric($this->plugin->txt('medium_height'))
            ->withAdditionalTransformation($this->refinery->kindlyTo()->int())
            ->withValue($medium->getHeight());

        $fields['play_in_modal'] = $factory->radio($this->plugin->txt('play_modal'))
            ->withOption('0', $this->plugin->txt('play_on_page'), $this->plugin->txt('play_on_page_info'))
            ->withOption('1', $this->plugin->txt('play_in_modal'), $this->plugin->txt('play_in_modal_info'))
            ->withValue($medium->getPlayInModal());

        $fields['play_with_pause'] = $factory->radio($this->plugin->txt('play_pause'))
            ->withOption('1', $this->plugin->txt('play_with_pause'), $this->plugin->txt('play_with_pause_info'))
            ->withOption('0', $this->plugin->txt('play_without_pause'), $this->plugin->txt('play_without_pause_info'))
            ->withValue($medium->getPlayWithPause());


        $sections['details'] = $factory->section($fields, $this->plugin->txt('settings_details'));

        return $this->ui_factory->input()->container()->form()->standard($this->ctrl->getFormAction($this), $sections)
            ->withSubmitCaption($this->lng->txt($create ? 'create' : 'save'));
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


    /**
     * Get HTML for element
     *
     * @param string    $a_mode page mode (edit, presentation, print, preview, offline)
     * @return string   html code
     */
    public function getElementHTML(string $a_mode, array $a_properties, string $plugin_version): string
    {
        $info = [];
        $params = [];
        $tpl = $this->plugin->getTemplate("tpl.page_block.html");

        $medium = Medium::fromProperties($this->getPageId(), $a_properties);

        $limit = new Limit($this->getParentId(), $this->getPageId(), $medium->getFileId(), $this->user->getId(), $medium->getLimitPlays(), true);

        $this->setMode($this->getViewMode());
        switch ($this->getMode()) {
            case self::VIEW_PRESENTATION:
            case self::VIEW_PREVIEW:

                //
                // Show the embedded player
                //
                iljQueryUtil::initjQuery();
                iljQueryUtil::initjQueryUI();

                // get usage and playing status
                // adjust the context and limit in preview
                if ($this->getViewMode() == self::VIEW_PREVIEW) {
                    $limit = $limit->setPlays(null);
                    $limit_context = LimitContext::from(LimitContext::SESSION);
                } else {
                    $limit = $this->plugin->factory()->LimitRepo($this->getParentId())->effective($limit);
                    $limit_context = $medium->getLimitContext();
                }

                // get the usage and status for the context
                $usage_repo = $this->plugin->factory()->usageRepo($this->getParentId(), $this->getPageId(), $medium->getFileId(), $limit_context);
                $usage = $usage_repo->get($this->user->getId());
                $usage->setPageView((bool) $a_properties['play_pause']);

                $status = $usage->getStatus($limit->getPlays(), $medium->getPlayWithPause());

                if ($medium->getPlayInModal()) {
                    // show the player and pause/volume in a modal
                    // open the modal by play or continue
                    $html = $this->getElementPlayerHTML($medium, $limit, $limit_context);
                    $controls = ($medium->getPlayWithPause() ? ['pause', 'volume'] : ['volume']);
                    $html .= $this->getElementControlsHTML($medium, $usage, $status, $controls);

                    $modal = ilModalGUI::getInstance();
                    $modal->setId('limplyModal' . $medium->getFileId());
                    $modal->setHeading($a_properties['medium_title']);
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

                // prepare javascript
                $tpl->setVariable('MEDIUM_ID', $medium->getFileId());
                $texts = array(
                    'test' => 'Hallo'
                );
                $this->tpl->addOnLoadCode('il.PCLimitedMediaPlayerPage.initPage(' . json_encode($texts) . ');');
                break;

            case self::VIEW_EDIT:
            case self::VIEW_PRINT:
            case self::VIEW_OFFLINE:
            default:
                //
                // Show only a representation with metadata
                //
                $info = array(
                    $this->plugin->txt('medium_file') => $medium->getFileId(),
                    $this->plugin->txt('limit_plays') => $medium->getLimitPlays(),
                    $this->plugin->txt('limit_context') => $this->plugin->txt('limit_context_' . $medium->getLimitContext()->value()),
                    $this->plugin->txt('play_mode') => $this->plugin->txt($medium->getPlayInModal() ? 'play_in_modal' : 'play_on_page'),
                    $this->plugin->txt('play_pause') => $medium->getPlayWithPause() ?
                        $this->plugin->txt('play_with_pause') : $this->plugin->txt('play_without_pause')
                );

                $usage = null;
                break;
        }

        // show info block
        $tpl->setVariable('INFO', $this->getElementInfoHTML($medium, $limit, $usage, $info));

        // always show the title
        $tpl->setCurrentBlock('title');
        $tpl->setVariable("TITLE", $medium->getTitle());
        $tpl->parseCurrentBlock();
        return $tpl->get();
    }

    protected function getElementPlayerHTML(Medium $medium, Limit $limit, LimitContext $limit_context): string
    {
        $tpl = $this->getPlugin()->getTemplate("tpl.page_player.html");

        // media iframe

        $url = $this->ctrl->getLinkTargetByClass([ilUIPluginRouterGUI::class, ilPCLimitedMediaPlayerGUI::class]);
        $params = [
            'parent_id' => $this->getParentId(),
            'page_id' => $this->getPageId(),
            'file_id' => $medium->getFileId(),
            'preview_id' => $medium->getPreviewId(),
            'height' => $medium->getHeight(),
            'width' => $medium->getWidth(),
            'play_with_pause' => $medium->getPlayWithPause(),
            'limit_context' => $limit_context->value(),
            'limit_plays' => (int) $limit->getPlays(),
        ];
        foreach ($params as $name => $value) {
            $url = ilUtil::appendUrlParameterString($url, $name . '=' . $value, true);
        }
        $tpl->setVariable('PLAYER_URL', $url);
        $tpl->setVariable('PLAYER_WIDTH', max($medium->getWidth(), 200));
        $tpl->setVariable('PLAYER_HEIGHT', max($medium->getHeight(), 50));

        return $tpl->get();
    }

    /**
     * @param string[] $controls ('play', 'pause', 'continue', 'volume')
     */
    protected function getElementControlsHTML(Medium $medium, Usage $usage, Status $status, array $controls): string
    {
        $preferences_repo = $this->plugin->factory()->preferencesRepo();

        if ($status->value() == Status::LIMIT) {
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
    protected function getElementInfoHTML(Medium $medium, Limit $limit, ?Usage $usage = null, $info = []): string
    {
        $tpl = $this->getPlugin()->getTemplate("tpl.page_info.html");

        if (isset($usage)) {
            if ($this->getViewMode() == self::VIEW_PREVIEW) {
                $limit_plays_suffix = $this->plugin->txt('limit_plays_preview');
            } else {
                $limit_plays_suffix = $this->plugin->txt('limit_plays_' . $medium->getLimitContext()->value());

                if (!$limit->isDefault()) {
                    $limit_plays_suffix .= ' ' . $this->plugin->txt('limit_plays_adapted');
                }
            }

            $tpl->setVariable("MAX_PLAYS", ($limit->getPlays() ?? $this->plugin->txt('runtime_no_limit'))
                . ' ' . $limit_plays_suffix);
            $tpl->setVariable("MAX_PLAYS_TEXT", $this->plugin->txt("runtime_max_plays"));

            $tpl->setVariable("CURRENT_PLAYS", $usage->getPlays());
            $tpl->setVariable("CURRENT_PLAYS_TEXT", $this->plugin->txt("runtime_plays"));

            $tpl->setVariable("CURRENT_SECONDS", max((int) $usage->getSeconds(), 0));
            $tpl->setVariable("CURRENT_SECONDS_TEXT", $this->plugin->txt("runtime_seconds"));
        }

        if (!empty($info)) {
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
     * Get the mode for viewing the element
     */
    protected function getViewMode(): string
    {
        switch ($this->ctrl->getCmdClass()) {
            case strtolower(ilAssQuestionPageGUI::class):
            case strtolower(ilTestExpressPageObjectGUI::class):
                return self::VIEW_EDIT;

            case strtolower(ilAssQuestionPreviewGUI::class):
                return self::VIEW_PREVIEW;

            case strtolower(ilObjTestGUI::class):
                switch ($this->ctrl->getCmd()) {
                    case 'preview':
                        return self::VIEW_PREVIEW;

                    case 'print':
                    default:
                        return self::VIEW_PRINT;
                }
                return self::VIEW_PRESENTATION;

            case strtolower(ilTestPlayerFixedQuestionSetGUI::class):
            case strtolower(ilTestPlayerRandomQuestionSetGUI::class):
                return self::VIEW_PRESENTATION;

            case strtolower(ilObjQuestionPoolGUI::class):
            case strtolower(ilTestScoringGUI::class):
            case strtolower(ilTestScoringByQuestionsGUI::class):
            case strtolower(ilTestEvaluationGUI::class):
            case strtolower(ilTestSubmissionReviewGUI::class):
            default:
                return self::VIEW_PRINT;
        }
    }

    /**
     * Get the ID of the current page
     * Currently equal to question id, only available in editor, preview and print view
     */
    protected function getPageId(): int
    {
        return $this->getPlugin()->getPageId();

        //        if ($this->getViewMode() == self::VIEW_PRESENTATION) {
        //
        //            $ref_id = $this->get->integer('ref_id', 0);
        //            $sequence = $this->get->integer('sequence', 0);
        //
        //            $sequence_obj = $this->plugin->factory()->testSequence($ref_id);
        //
        //            if (empty($sequence)) {
        //                $sequence = $sequence_obj->getFirstSequence();
        //            }
        //            return $sequence_obj->getQuestionForSequence($sequence);
        //
        //        } else {
        //            return $this->get->integer('q_id', 0);
        //        }
    }

    /**
     * Get the id of the parent object
     * Currently the object id of the pool or test
     */
    protected function getParentId(): int
    {
        return $this->getPlugin()->getParentId();
        //return ilObject::_lookupObjectId($this->get->integer('ref_id', 0));
    }
}
