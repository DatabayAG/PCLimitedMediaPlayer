<?php
/**
 * Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

include_once("./Services/COPage/classes/class.ilPageComponentPluginGUI.php");
include_once("./Services/MediaObjects/classes/class.ilObjMediaObject.php");
 
/**
 * Page Component Limited Media Player plugin GUI
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 *
 * @ilCtrl_isCalledBy ilPCLimitedMediaPlayerPluginGUI: ilPCPluggedGUI
 */
class ilPCLimitedMediaPlayerPluginGUI extends ilPageComponentPluginGUI
{
    const VIEW_EDIT = 'edit';
    const VIEW_OFFLINE = 'offline';
    const VIEW_PRINT = 'print';
    const VIEW_PRESENTATION = 'presentation';
    const VIEW_PREVIEW = 'preview';


    /** @var ilPCLimitedMediaPlayerPlugin $plugin */
    protected $plugin;

    /** @var string $errorMessage */
    protected $errorMessage;

    /** @var  ilPCPlugged $contentObj */
    private $contentObj;

    /** @var  ilAssQuestionPage $pageObj */
    private $pageObj;

    /** @var  ilPCMediaObject */
    private $pageMediaObj;


	/**
	 * Execute command
	 *
	 * @param
	 * @return
	 */
	public function executeCommand()
	{
		global $ilCtrl;
		
		$next_class = $ilCtrl->getNextClass();

		switch($next_class)
		{
			default:
				// perform valid commands
				$cmd = $ilCtrl->getCmd();
				if (in_array($cmd, array("create", "edit", "update", "cancel")))
				{
					$this->$cmd();
				}
				break;
		}
	}
	
	
	/**
	 * Show the creation form
	 */
	public function insert()
	{
		global $tpl;
		
		$form = $this->initForm(true);
		$tpl->setContent($form->getHTML());
	}
	
	/**
	 * Save the new element
	 */
	public function create()
	{
		global $tpl, $lng, $ilCtrl;
	
		$form = $this->initForm(true);
		if ($form->checkInput())
		{
			if ($this->saveForm($form, true))
			{
				ilUtil::sendSuccess($lng->txt("msg_obj_created"), true);
			}
			else
            {
                ilUtil::sendFailure($this->errorMessage, true);
            }
            $this->returnToParent();
		}
		$form->setValuesByPost();
		$tpl->setContent($form->getHtml());
	}
	
	/**
	 * Show the edit form
	 */
	public function edit()
	{
		global $tpl;
		
		$this->setTabs("edit");
        $form = $this->initForm();
		$tpl->setContent($html . $form->getHTML());
	}
	
	/**
	 * Update the edited element
	 */
	public function update()
	{
		global $tpl, $lng, $ilCtrl;
	
		$form = $this->initForm(false);
		if ($form->checkInput())
		{
 			if ($this->saveForm($form, false))
			{
				ilUtil::sendSuccess($lng->txt("msg_obj_modified"), true);
			}
			else
            {
                ilUtil::sendFailure($this->errorMessage, true);
            }
            $this->returnToParent();
		}
		$form->setValuesByPost();
		$tpl->setContent($form->getHtml());
	}

    /**
     * Save the posted properties
     * @param ilPropertyFormGUI     $a_form
     * @param bool                  $a_create
     * @return bool
     */
	protected function saveForm($a_form, $a_create)
    {
        // save the properties
        $properties = array_merge($this->getProperties(), array(
            'medium_title' => $a_form->getInput('medium_title'),
            'limit_plays' => $a_form->getInput('limit_plays'),
            'limit_context' => $a_form->getInput('limit_context'),
            'medium_width' => $a_form->getInput('medium_width'),
            'medium_height' => $a_form->getInput('medium_height'),
            'play_modal' => $a_form->getInput('play_modal'),
            'play_pause' => $a_form->getInput('play_pause')
        ));
        if ($a_create)
        {
            $success = $this->createElement($properties);
        }
        else
        {
            $success = $this->updateElement($properties);
        }

        if (!$success)
        {
            $this->errorMessage = $this->txt('err_save_properties');
            return false;
        }

        // finish if no media files are uploaded
        if (empty($_FILES['medium_file']['tmp_name'])
            && empty($_FILES['medium_startpic']['tmp_name'])
            && empty($_POST['medium_startpic_delete']))
        {
            return true;
        }

        // try to update or create the media object
        // an existing media object is replaced by a clone to avoid side effects in cloned questions
        try
        {
            $pageMediaObj = $this->getPageMediaObject();
            if (!empty($pageMediaObj))
            {
                $mediaObj = $this->replaceMediaObject($pageMediaObj);
            }
            else
            {
                $pageMediaObj = $this->addPageMediaObject();
                $mediaObj = $pageMediaObj->getMediaObject();
            }

            if (!empty($_FILES['medium_file']['tmp_name']))
            {
                $file_name = ilObjMediaObject::fixFilename($_FILES['medium_file']['name']);
                $file = $mediaObj->getDataDirectory()."/".$file_name;
                $format = ilObjMediaObject::getMimeType($file);
                ilUtil::moveUploadedFile($_FILES['medium_file']['tmp_name'], $file_name, $file);

                // set the standard item (a placeholder)
                $this->setMediaStandardItem($mediaObj);

                // set the fullscreen item (the real medium)
                $mediaItem = $mediaObj->getMediaItem('Fullscreen');
                if (empty($mediaItem))
                {
                    $mediaItem = new ilMediaItem();
                    $mediaItem->setPurpose('Fullscreen');
                    $mediaObj->addMediaItem($mediaItem);
                }
                @unlink($mediaItem->getLocation());
                $mediaItem->setLocation($file_name);
                $mediaItem->setLocationType("LocalFile");
                $mediaItem->setFormat(ilObjMediaObject::getMimeType($file));
            }

            if (!empty($_FILES['medium_startpic']['tmp_name']))
            {
                $mediaObj->uploadVideoPreviewPic($_FILES['medium_startpic']);
            }
            elseif (!empty($_POST['medium_startpic_delete']))
            {
                @unlink($mediaObj->getVideoPreviewPic());
            }

            $mediaObj->setTitle($properties['medium_title']);
            $mediaObj->update();

        }
        catch (Exception $e)
        {
            $this->errorMessage = $this->txt('err_save_media_obj');
            return false;
        }

        return true;
    }



	/**
	 * Init editing form
	 *
	 * @param        int        $a_mode        Edit Mode
	 */
	protected function initForm($a_create = false)
	{
		global $lng, $ilCtrl;

		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();

		// title
        $medium_title = new ilTextInputGUI($this->txt('medium_title'),'medium_title');
        $medium_title->setRequired(true);
        $form->addItem($medium_title);

        // medium file
        $medium_file = new ilFileInputGUI($this->txt('medium_file'), 'medium_file');
        //$medium_file->setSuffixes(ilObjMediaObject::getRestrictedFileTypes());
        $medium_file->setInfo($this->txt('medium_file_info'));
        $form->addItem(($medium_file));


        // limit plays
        $limit_plays = new ilNumberInputGUI($this->txt('limit_plays'), 'limit_plays');
        $limit_plays->setSize(5);
        $limit_plays->setMinValue(1);
        $form->addItem($limit_plays);

        // limit context
        $limit_context_testpass = new ilRadioOption($this->txt('limit_context_testpass'), 'testpass');
        $limit_context_session = new ilRadioOption($this->txt('limit_context_session'), 'session');
        $limit_context_user = new ilRadioOption($this->txt('limit_context_user'), 'user');
        $limit_context = new ilRadioGroupInputGUI($this->txt('limit_context'),'limit_context');
        $limit_context->addOption($limit_context_testpass);
        $limit_context->addOption($limit_context_session);
        $limit_context->addOption($limit_context_user);
        $form->addItem($limit_context);

        // details header
        $settings_details = new ilFormSectionHeaderGUI();
        $settings_details->setTitle($this->txt('settings_details'));
        $form->addItem($settings_details);

        // start picture
        $medium_startpic = new ilImageFileInputGUI($this->txt('medium_startpic'), 'medium_startpic');
        $medium_startpic->setInfo($this->txt('medium_startpic_info'));
        $medium_startpic->setALlowDeletion(true);
        $form->addItem($medium_startpic);

        // width
        $medium_width = new ilNumberInputGUI($this->txt('medium_width'), 'medium_width');
        $medium_width->setSize(5);
        $medium_width->setDecimals(0);
        $form->addItem($medium_width);

        // height
        $medium_height = new ilNumberInputGUI($this->txt('medium_height'), 'medium_height');
        $medium_height->setSize(5);
        $medium_height->setDecimals(0);
        $form->addItem($medium_height);

        // play mode
        $play_on_page = new ilRadioOption($this->txt('play_on_page'), '0');
        $play_on_page->setInfo($this->txt('play_on_page_info'));
        $play_in_modal = new ilRadioOption($this->txt('play_in_modal'), '1');
        $play_in_modal->setInfo($this->txt('play_in_modal_info'));
        $play_modal = new ilRadioGroupInputGUI($this->txt('play_mode'), 'play_modal');
        $play_modal->addOption($play_on_page);
        $play_modal->addOption($play_in_modal);
        $form->addItem($play_modal);

        // play pause
        $play_with_pause = new ilRadioOption($this->txt('play_with_pause'), '1');
        $play_with_pause->setInfo($this->txt('play_with_pause_info'));
        $play_without_pause = new ilRadioOption($this->txt('play_without_pause'), '0');
        $play_without_pause->setInfo($this->txt('play_without_pause_info'));
        $play_pause = new ilRadioGroupInputGUI($this->txt('play_pause'), 'play_pause');
        $play_pause->addOption($play_with_pause);
        $play_pause->addOption($play_without_pause);
        $form->addItem($play_pause);

        // add debugging properties
        if ($this->plugin->getDebug())
        {
            $settings_debug = new ilFormSectionHeaderGUI();
            $settings_debug->setTitle($this->txt('settings_debug'));
            $form->addItem($settings_debug);

            foreach($this->getDebugProperties() as $name => $value)
            {
                $prop = new ilNonEditableValueGUI($name);
                $prop->setValue($value);
                $form->addItem($prop);
            }
        }

        if ($a_create)
        {
            $limit_plays->setValue(1);
            $limit_context->setValue('testpass');
            $play_modal->setValue(0);
            $play_pause->setValue(1);
        }
        else
		{
			$prop = $this->getProperties();

            $medium_title->setValue($prop['medium_title']);
            $limit_plays->setValue($prop['limit_plays']);
            $limit_context->setValue($prop['limit_context']);
            $medium_width->setValue($prop['medium_width']);
            $medium_height->setValue($prop['medium_height']);
            $play_modal->setValue($prop['play_modal']);
            $play_pause->setValue($prop['play_pause']);

            if ($pageMediaObj = $this->getPageMediaObject($prop))
            {
                /** @var ilObjMediaObject $mediaObj */
                if ($mediaObj = $pageMediaObj->getMediaObject())
                {
                    $medium_startpic->setImage($mediaObj->getVideoPreviewPic());
                }
            }

        }


        // save and cancel commands
		if ($a_create)
		{
			$this->addCreationButton($form);
			$form->addCommandButton("cancel", $lng->txt("cancel"));
			$form->setTitle($this->txt("cmd_insert"));
		}
		else
		{
			$form->addCommandButton("update", $lng->txt("save"));
			$form->addCommandButton("cancel", $lng->txt("cancel"));
			$form->setTitle($this->txt("edit_limited_media_player"));
		}

		$form->setMultipart(true);
		$form->setFormAction($ilCtrl->getFormAction($this));
		return $form;
	}


	/**
	 * Cancel
	 */
	public function cancel()
	{
		$this->returnToParent();
	}


	/**
	 * Set tabs
	 *
	 * @param
	 * @return
	 */
	public function setTabs($a_active)
	{
		global $ilTabs, $ilCtrl;

		$ilTabs->addTab("edit", $this->txt("settings"), $ilCtrl->getLinkTarget($this, "edit"));
		$ilTabs->activateTab($a_active);
	}

    /**
     * Get a plugin text
     * @param $a_var
     * @return mixed
     */
    protected function txt($a_var)
    {
        return $this->getPlugin()->txt($a_var);
    }


	/**
	 * Get HTML for element
	 *
	 * @param string    page mode (edit, presentation, print, preview, offline)
	 * @return string   html code
	 */
	public function getElementHTML($a_mode, array $a_properties, $a_plugin_version)
	{
	    /** @var ilTemplate $tpl */
		global $tpl, $ilCtrl, $ilUser;

		$info = array();
		$params = array();
        $btpl = $this->getPlugin()->getTemplate("tpl.page_block.html");

		$this->setMode($this->getViewMode());
        switch ($this->getMode())
        {
            case self::VIEW_PRESENTATION:
            case self::VIEW_PREVIEW:
                //
                // Show the embedded player
                //

                require_once ('Services/jQuery/classes/class.iljQueryUtil.php');
                iljQueryUtil::initjQuery();
                iljQueryUtil::initjQueryUI();

                /** @var ilPCMediaObject $pgmob */
                $pgmob = $this->getPageMediaObject($a_properties);
                if (!is_object($pgmob)) break;

                /** @var ilObjMediaObject $mob */
                $mob = $pgmob->getMediaObject();
                if (!is_object($mob))  break;

                /** @var ilMediaItem $item */
                $item = $mob->getMediaItem('Fullscreen');
                if (!is_object($item)) break;

                // get usage and playing status
                // adjust the context and limit in preview
                require_once (__DIR__ . "/class.ilLimitedMediaPlayerUsage.php");
                if ($this->getViewMode() == self::VIEW_PREVIEW)
                {
                    $limit_plays = 0;
                    $limit_context = ilLimitedMediaPlayerUsage::CONTEXT_SESSION;
                }
                else
                {
                    $limit_plays = $a_properties['limit_plays'];
                    $limit_context = $a_properties['limit_context'];
                }

                // get the usage and status for the context
                $usage = new ilLimitedMediaPlayerUsage($this->getParentId(), $this->getPageId(), $mob->getId(), $ilUser->getId(), $limit_context);
                $usage->handlePageView($a_properties['play_pause']);
                $status = $usage->getStatus((int) $limit_plays, (bool) $a_properties['play_pause']);

                if ($a_properties['play_modal'])
                {
                    // show the player and pause/volume in a modal
                    // open the modal by play or continue
                    $html = $this->getElementPlayerHTML($mob, $item, $a_properties, $limit_plays, $limit_context);
                    $controls = ($a_properties['play_pause'] ? array('pause', 'volume') : array('volume'));
                    $html .= $this->getElementControlsHTML($mob, $usage, $status, $controls);

                    require_once('Services/UIComponent/Modal/classes/class.ilModalGUI.php');
                    $modal = ilModalGUI::getInstance();
                    $modal->setId('limplyModal'. $mob->getId());
                    $modal->setHeading($a_properties['medium_title']);
                    $modal->setBody($html);
                    $modal->setType(ilModalGUI::TYPE_LARGE);
                    $btpl->setVariable('PLAYER', $modal->getHTML());

                    $controls = $a_properties['play_pause'] ? array('play','continue') :  array('play');
                    $btpl->setVariable('CONTROLS', $this->getElementControlsHTML($mob, $usage, $status, $controls));
                }
                else
                {
                    // show the player and all controls embedded
                    $btpl->setVariable('PLAYER', $this->getElementPlayerHTML($mob, $item, $a_properties, $limit_plays, $limit_context));

                    $controls = $a_properties['play_pause'] ? array('play','pause','continue','volume') :  array('play','volume');
                    $btpl->setVariable('CONTROLS', $this->getElementControlsHTML($mob, $usage, $status, $controls));
                }

                // prepare javascript
                $btpl->setVariable('MEDIUM_ID', $mob->getId());
                $texts = array(
                    'test' => 'Hallo'
                );
                $tpl->addOnLoadCode('il.PCLimitedMediaPlayerPage.initPage('.json_encode($texts).');');
                break;

            case self::VIEW_EDIT:
            case self::VIEW_PRINT:
            case self::VIEW_OFFLINE:
                //
                // Show only a representation with meta data
                //
                $info = array(
                    $this->txt('limit_plays') => $a_properties['limit_plays'],
                    $this->txt('limit_context') => $this->txt('limit_context_'.$a_properties['limit_context']),
                    $this->txt('play_mode') => $this->txt($a_properties['play_modal'] ? 'play_in_modal' : 'play_on_page'),
                    $this->txt('play_pause') => $a_properties['play_pause'] ?
                        $this->txt('play_with_pause') : $this->txt('play_without_pause')
                );
                $usage = null;
                break;
        }

        // add debugging information
        if (ilPCLimitedMediaPlayerPlugin::DEBUG)
        {
            $info = array_merge($info, $this->getDebugProperties());
        }

        // show info block
        $btpl->setVariable('INFO', $this->getElementInfoHTML($a_properties, $info, $usage));

        // always show the title
        $btpl->setCurrentBlock('title');
        $btpl->setVariable("TITLE", $a_properties['medium_title']);
        $btpl->parseCurrentBlock();
        return $btpl->get();
	}

    /**
     * @param ilObjMediaObject  $mob
     * @param ilMediaItem       $item
     * @param array             $a_properties
     * @param int               $limit_plays
     * @param string            $limit_context
     */
	protected function getElementPlayerHTML($mob, $item, $a_properties, $limit_plays, $limit_context)
    {
        $tpl = $this->getPlugin()->getTemplate("tpl.page_player.html");

        // media iframe
        $url = $this->plugin->getPlayerUrl().'?cmd=show';
        $params = array(
            'parent_id' => (int) $this->getParentId(),
            'page_id' => (int) $this->getPageId(),
            'mob_id' => (int) $mob->getId(),
            'file' => (string) $item->getLocation(),
            'mime' => (string) $item->getFormat(),
            'startpic' => (string) $mob->getVideoPreviewPic(true),
            'height' => (int) $a_properties['medium_height'],
            'width' => (int) $a_properties['medium_width'],
            'play_pause' => (bool) $a_properties['play_pause'],
            'limit_context' => (string) $limit_context,
            'limit_plays' => (int) $limit_plays,
        );
        foreach ($params as $name => $value)
        {
            $url = ilUtil::appendUrlParameterString($url, $name . '=' . $value, true);
        }
        $tpl->setVariable('PLAYER_URL', $url);
        $tpl->setVariable('PLAYER_WIDTH', max((int) $a_properties['medium_width'], 200));
        $tpl->setVariable('PLAYER_HEIGHT', max((int) $a_properties['medium_height'], 50));

        return $tpl->get();
    }

    /**
     * @param ilObjMediaObject          $mob
     * @param ilLimitedMediaPlayerUsage $usage
     * @param string                    $status
     * @param array                     $controls ('play', 'pause', 'continue', 'volume')
     * @param bool
     */
    protected function getElementControlsHTML($mob, $usage, $status, $controls)
    {
        if ($status == ilLimitedMediaPlayerUsage::STATUS_LIMIT)
        {
            return '';
        }

        $tpl = $this->getPlugin()->getTemplate("tpl.page_controls.html");

        if (in_array('play', $controls))
        {
            $tpl->setVariable('ID_PLAY', $mob->getId());
            $tpl->setVariable('TXT_PLAY', $this->txt("runtime_play"));
            $tpl->setVariable('STATUS_PLAY', $status == ilLimitedMediaPlayerUsage::STATUS_START ? '' : 'hidden');
        }

        if (in_array('pause', $controls))
        {
            $tpl->setVariable('ID_PAUSE', $mob->getId());
            $tpl->setVariable('TXT_PAUSE', $this->txt("runtime_pause"));
            $tpl->setVariable('STATUS_PAUSE', $status == ilLimitedMediaPlayerUsage::STATUS_PLAY ? '' : 'hidden');
        }

        if (in_array('continue', $controls))
        {
            $tpl->setVariable('ID_CONTINUE', $mob->getId());
            $tpl->setVariable('TXT_CONTINUE', $this->txt("runtime_continue"));
            $tpl->setVariable('STATUS_CONTINUE', $status == ilLimitedMediaPlayerUsage::STATUS_PAUSE ? '' : 'hidden');
        }

        if (in_array('volume', $controls))
        {
            $tpl->setVariable('ID_VOLUME', $mob->getId());
            $tpl->setVariable('ICON_VOLUME', ilUtil::getImagePath('icon_mob.svg'));
            $tpl->setVariable('TXT_VOLUME', $this->txt('runtime_volume'));
            $tpl->setVariable('VALUE_VOLUME', $usage->getVolume() * 100);
        }

        return $tpl->get();
    }

    /**
     * get the HTML code of the emelent information
     * @param array                         $a_properties
     * @param array                         $info (text => value)
     * @param ilLimitedMediaPlayerUsage     $usage
     */
    protected function getElementInfoHTML($a_properties, $info = array(), $usage = null)
    {
        $tpl = $this->getPlugin()->getTemplate("tpl.page_info.html");

        if (isset($usage))
        {
            if ($this->getViewMode() == self::VIEW_PREVIEW)
            {
                $limit_plays_suffix = $this->txt('limit_plays_preview');
            }
            else
            {
                $limit_plays_suffix = $this->txt('limit_plays_'.$a_properties['limit_context']);
            }

            $tpl->setVariable("MAX_PLAYS", ($a_properties['limit_plays'] ? (int) $a_properties['limit_plays'] : $this->txt('runtime_no_limit'))
                . ' ' . $limit_plays_suffix);
            $tpl->setVariable("MAX_PLAYS_TEXT", $this->txt("runtime_max_plays"));

            $tpl->setVariable("CURRENT_PLAYS",(int) $usage->getPlays());
            $tpl->setVariable("CURRENT_PLAYS_TEXT", $this->txt("runtime_plays"));

            $tpl->setVariable("CURRENT_SECONDS", max((int) $usage->getSeconds(), 0));
            $tpl->setVariable("CURRENT_SECONDS_TEXT", $this->txt("runtime_seconds"));
        }

        if (!empty($info))
        {
            // show the static information
            foreach ($info as $text => $value)
            {
                $tpl->setCurrentBlock('info');
                $tpl->setVariable('INFO_TEXT', $text);
                $tpl->setVariable('INFO_VALUE', $value);
                $tpl->parseCurrentBlock();
            }
        }

        return $tpl->get();
    }

    /**
     * Get the id of the current question
     * @return int|null
     */
    protected function getQuestionId()
    {
        return $_GET['q_id'];
    }

    /**
     * Get the Id of the current page
     * Currently eqal to question id, only available in editor, preview and print view
     * @return int|null
     */
    protected function getPageId()
    {
        global $ilCtrl, $ilUser, $ilDB, $ilPluginAdmin, $lng;

        if ($this->getViewMode() == self::VIEW_PRESENTATION)
        {
            require_once('Modules/Test/classes/class.ilTestSessionFactory.php');
            require_once('Modules/Test/classes/class.ilTestSequenceFactory.php');
            $testObj = new ilObjTest($_GET['ref_id']);
            $sessionFactory = new ilTestSessionFactory($testObj);
            $sequenceFactory = new ilTestSequenceFactory($ilDB, $lng, $ilPluginAdmin, $testObj);
            $sessionObj = $sessionFactory->getSessionByUserId($ilUser->getId());
            $sequenceObj = $sequenceFactory->getSequence($sessionObj);
            $sequenceObj->loadFromDb();
            $sequenceObj->loadQuestions();

            $sequence = $_GET["sequence"];
            if (empty($sequence))
            {
                $sequence = $sequenceObj->getFirstSequence();
            }

            // only 5.0 - this parameter is not set in 5.1+ when a question is shown
            switch ($_GET["activecommand"])
            {
                case "next":
                    $sequence = $sequenceObj->getNextSequence($sequence);
                    break;
                case "previous":
                    $sequence = $sequenceObj->getPreviousSequence($sequence);
                    break;
            }
            return $sequenceObj->getQuestionForSequence($sequence);
        }
        else
        {
            return $_GET['q_id'];
        }
    }

    /**
     * Get the type of the parent object
     * Currently onl 'qpl'
     */
    protected function getParentType()
    {
        return 'qpl';
    }


    /**
     * Get the mode for viewing the element
     */
    protected function getViewMode()
    {
        global $ilCtrl;
        switch ($ilCtrl->getCmdClass())
        {
            case 'ilassquestionpagegui':
            case 'iltestexpresspageobjectgui':
                return self::VIEW_EDIT;

            case 'ilassquestionpreviewgui':
                return self::VIEW_PREVIEW;

            case 'ilobjquestionpoolgui':
            case 'iltestscoringgui':
            case 'iltestscoringbyquestionsgui':
            case 'iltestevaluationgui':
            case 'iltestsubmissionreviewgui':
                return self::VIEW_PRINT;

            case 'ilobjtestgui':
                switch ($ilCtrl->getCmd)
                {
                     case 'preview':
                        return self::VIEW_PREVIEW;

                    case 'print':
                    default:
                        return self::VIEW_PRINT;
                }

            case 'iltestplayerfixedquestionsetgui':
            case 'iltestplayerrandomquestionsetgui':
            case 'iltestplayerdynamicquestionsetgui':
                return self::VIEW_PRESENTATION;

            default:
                return self::VIEW_PRINT;
        }
    }


    /**
     * Get the hierarchical ID of the page content
     * @return int|null
     */
    protected function getHierId()
    {
        /** @var ilPCPluggedGUI $pcgui */
        if ($pcgui = $this->getPCGUI())
        {
            return $pcgui->getHierId();
        }
        return null;
    }

    /**
     * Get the id of the parent object
     * Curently the object id of the pool or test
     */
    protected function getParentId()
    {
        return ilObject::_lookupObjectId($_GET['ref_id']);
    }

    /**
     * Get the content object of this page content
     * @return ilPCPlugged|null
     */
    protected function getContentObject()
    {
        if (isset($this->contentObj))
        {
            return $this->contentObj;
        }

        /** @var ilPCPluggedGUI $pcgui */
        if ($pcgui = $this->getPCGUI())
        {
            $this->contentObj = $pcgui->getContentObject();
            if (isset($this->contentObj))
            {
                $this->contentObj->setPcId($this->contentObj->readPCId());
            }
        }

        return $this->contentObj;
    }

    /**
     * get the page object
     * @return ilPageObject|null
     */
    protected function getPageObject()
    {
        if (isset($this->pageObj))
        {
            return $this->pageObj;
        }

        if ($contentObj = $this->getContentObject())
        {
            // get the page object from editor context
            // this should be possible for 'create' 'edit' and 'update'
            $this->pageObj = $contentObj->getPage();
        }

        if (!isset($this->pageObj))
        {
            $page_id = $this->getPageId();
            if (!empty($page_id))
            {
                // get the page object from question id in url
                // this should be possible in single question preview and in test run
                $this->pageObj = new ilAssQuestionPage($page_id);
            }
        }

        return $this->pageObj;
    }

    /**
     * Get the linked media object from the current page
     * @param array|null    $a_properties   (must be provided when called by getElementHTML)
     * @return ilPCMediaObject|null
     */
    protected function getPageMediaObject($a_properties = null)
    {
        if (!isset($a_properties))
        {
            $a_properties = $this->getProperties();
        }

        if ($a_properties['medium_pcid'])
        {
            if ($pageObj = $this->getPageObject())
            {
                $pageObj->buildDom();

                /** @var ilPCMediaObject $pageMediaObj */
                $pageMediaObj = $pageObj->getContentObject('', $a_properties['medium_pcid']);

                return $pageMediaObj;
            }
        }

        return null;
    }

    /**
     * Add a new media object to the page
     * @return ilPCMediaObject
     */
    protected function addPageMediaObject()
    {
        $pageObj = $this->getPageObject();
        $pageObj->read();
        $pageObj->buildDom(true);
        $pageObj->addHierIDs();

        $mediaObj = new ilObjMediaObject();
        $mediaObj->create();
        $mediaObj->createDirectory();
        $mediaItem = new ilMediaItem();
        $mediaItem->setPurpose('Standard');
        $mediaObj->addMediaItem($mediaItem);
        $mediaObj->update();

        require_once('Services/COPage/classes/class.ilPCMediaObject.php');
        $pageMediaObj = new ilPCMediaObject($pageObj);
        $pageMediaObj->setDom($pageObj->getDom());
        $pageMediaObj->setMediaObject($mediaObj);
        $pageMediaObj->createAlias($pageObj, $this->getHierId());
        $pageObj->update();

        // a new pcid is written to the dom in the page update
        $pageMediaObj->setPcId($pageMediaObj->readPCId());
        $pageMediaObj->disable();

        // refresh the pcid of the page media object in the page content of the plugin
        $contentObj = $this->getContentObject();
        $contentObj->setPage($pageObj);
        $contentObj->dom = $pageObj->getDom();
        $contentObj->setNode($pageObj->getContentNode('', $contentObj->getPCId()));

        $properties = (array) $contentObj->getProperties();
        $properties['medium_pcid'] =  $pageMediaObj->getPCId();
        $contentObj->setProperties($properties);
        $pageObj->update();

        return $pageMediaObj;
    }

    /**
     * Replace the media object reference by a clone if it is used on another page, too
     * This avoids side effects with other limited media
     * @param ilPCMediaObject   $pageMediaObject
     * @return ilObjMediaObject
     */
    protected function replaceMediaObject($pageMediaObject)
    {
        /** @var ilObjMediaObject $mediaObj */
        if ($mediaObj = $pageMediaObject->getMediaObject())
        {
            if (count($mediaObj->getUsages(false)) > 1)
            {
                $mediaObj = $mediaObj->duplicate();
            }
        }
        else
        {
            $mediaObj = new ilObjMediaObject();
            $mediaObj->create();
            $mediaObj->createDirectory();
            $mediaItem = new ilMediaItem();
            $mediaItem->setPurpose('Standard');
            $mediaObj->addMediaItem($mediaItem);
            $mediaObj->update();
        }

        $pageObj = $this->getPageObject();
        $pageObj->buildDom(true);
        $pageObj->addHierIDs();

        $pageMediaObject->setPage($pageObj);
        $pageMediaObject->setDom($pageObj->getDom());
        $pageMediaObject->setMediaObject($mediaObj);
        $pageMediaObject->setNode($pageObj->getContentNode('', $pageMediaObject->readPCId()));
        $pageMediaObject->updateObjectReference();

        $pageObj->update();

        return $mediaObj;
    }


    /**
     * Set the standard item of the medium
     * The standard item is displayed in the deactivated block of the media object
     * This function will get obsolete when the media object can be assigned in the background
     *
     * @param ilObjMediaObject  $mediaObj
     */
    protected function setMediaStandardItem($mediaObj)
    {
        $standardItem = $mediaObj->getMediaItem('Standard');
        if (empty($standardItem))
        {
            $standardItem = new ilMediaItem();
            $standardItem->setPurpose('Standard');
            $mediaObj->addMediaItem($standardItem);
        }
        $standard_name = "mcst_preview.svg";
        $standard_path = $mediaObj->getDataDirectory() . '/'. $standard_name;
        @unlink($standardItem->getLocation());
        @copy(ilUtil::getImagePath('mcst_preview.svg'), $standard_path);
        $standardItem->setLocation($standard_name);
        $standardItem->setLocationType("LocalFile");
        $standardItem->setHeight(0);
        $standardItem->setFormat(ilObjMediaObject::getMimeType($standard_path));
        $standardItem->setCaption($this->txt('medium_edit_notice'));
    }

    /**
     * Get debugging properties
     * @return array
     */
	protected function getDebugProperties()
    {
        if (!$this->plugin->getDebug())
        {
            return array();
        }

        $properties = array();
        $properties['debug_mode'] = $this->getMode();
        $properties['debug_page_id'] = $this->getPageId();


        /** @var ilPCPluggedGUI $pcgui */
        if ($pcgui = $this->getPCGUI())
        {
            $properties['debug_pcgui'] = get_class($pcgui);
            $properties['debug_hier_id'] = $pcgui->getHierId();

            /** @var ilPCPlugged $co */
            if ($co = $pcgui->getContentObject())
            {
                $properties['debug_co'] = get_class($co);
                $properties['debug_pcid'] = $co->getPCId();

                /** @var ilPageObject $pg */
                if ($pg = $co->getPage())
                {
                    $properties['debug_pg'] = get_class($pg);
                }
            }
        }

        return $properties;
    }

}

?>
