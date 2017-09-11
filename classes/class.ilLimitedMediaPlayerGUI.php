<?php
/**
 * Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

/**
 * GUI class for limited media player.
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 */
class ilLimitedMediaPlayerGUI
{
    protected $plugin;

    /**
     * @var string  Path to the mediaelement player
     */
    private $mejs_path = "lib/mediaelement-4.1.3";

	/**
	 * @var parameters stored with the limited media object, added to the request
	 */
	private $parent_id;
	private $page_id;
	private $mob_id;
	private $file;
	private $mime;
	private $startpic;
	private $height;
	private $width;
	private $limit_context;
	private $limit_plays;
	private $play_pause;

	/**
	 * @var internal status variables 
	 */
	private $usage = null;
	private $current_plays = 0;
    private $current_seconds = -1;
    private $status;
    private $volume;


	/**
	 * Constructor
	 * Initializes internal variables and objects
	 * Does not change anything
	 */
	function __construct()
	{
		global $tpl, $lng, $ilUser;

        require_once (__DIR__ . "/class.ilPCLimitedMediaPlayerPlugin.php");
        require_once (__DIR__ . "/class.ilLimitedMediaPlayerUsage.php");

		$this->plugin = new ilPCLimitedMediaPlayerPlugin();

		$this->parent_id = (int) $_GET["parent_id"];
		$this->page_id = (int) $_GET["page_id"];
		$this->mob_id = (int) $_GET["mob_id"];
		$this->file = (string) $_GET["file"];
        $this->mime = (string) $_GET["mime"];
        $this->startpic = (string) $_GET["startpic"];
		$this->height = (int) $_GET["height"];
		$this->width = (int) $_GET["width"];
        $this->play_pause = (bool) $_GET["play_pause"];
		$this->limit_context = (string) $_GET["limit_context"];
		$this->limit_plays = (int) $_GET["limit_plays"];

		// get the stored usage
		$this->usage = new ilLimitedMediaPlayerUsage($this->parent_id, $this->page_id, $this->mob_id, $ilUser->getId(), $this->limit_context);
	}

	
	/**
	 * Handle the player request
	 * The player is called from an iframe of the media object
	 * ilCtrl is not used 
	 */
	public function executeCommand()
	{
	    switch ($_GET['cmd'])
        {
            case 'show':
                $this->usage->handlePageView($this->play_pause);
                $this->current_plays = (int) $this->usage->getPlays();
                $this->current_seconds = (int) $this->usage->getSeconds();
                $this->status = (string) $this->usage->getStatus((int) $this->limit_plays, (bool) $this->play_pause);
                $this->volume = (float) $this->usage->getVolume();

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
		if (is_file('Services/WebAccessChecker/classes/class.ilWACSignedPath.php'))
		{
			require_once("Services/WebAccessChecker/classes/class.ilWACSignedPath.php");
		}

        $medium_path = './data/'.CLIENT_ID.'/mobs/mm_'. $this->mob_id . '/' . $this->file;
        if (class_exists('ilWACSignedPath'))
		{
			$medium_path = ilWACSignedPath::signFile($medium_path);
		}
		$medium_path = LIMPLY_BACKSTEPS . $medium_path;


        if ($this->startpic)
        {
            $startpic_path = './data/'.CLIENT_ID.'/mobs/mm_'. $this->mob_id . '/' . $this->startpic;
			if (class_exists('ilWACSignedPath'))
			{
				$startpic_path = ilWACSignedPath::signFile($startpic_path);
			}
			$startpic_path = LIMPLY_BACKSTEPS . $startpic_path;
		}
        else
        {
            $startpic_path = LIMPLY_BACKSTEPS . ilUtil::getImagePath('mcst_preview.svg');
        }

        /** @var ilTemplate $tpl */
        $tpl = $this->plugin->getTemplate("tpl.player.html");

        $tpl->setCurrentBlock('startpic');
        $tpl->setVariable("FILE", $startpic_path);
        $tpl->setVariable("HEIGHT", $this->height);
        $tpl->setVariable("WIDTH", $this->width);
        $tpl->parseCurrentBlock();

        // show only startpic if limit is reached
        if ($this->status == ilLimitedMediaPlayerUsage::STATUS_LIMIT)
        {
            $tpl->show();
            return;
        }

        $update_url = "player.php?cmd=update"
            ."&limit_plays=".$this->limit_plays
			."&limit_context=".$this->limit_context
			."&parent_id=".$this->parent_id
			."&page_id=".$this->page_id
			."&mob_id=".$this->mob_id;

        $volume_url = "player.php?cmd=volume";

        $config = array(
            'type' => substr($this->mime,0, 5) == 'audio' ? 'audio' : 'video',
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

        $tpl->setVariable("JQUERY_URL", $this->mejs_path.'/build/jquery.js');
        $tpl->setVariable("PLAYER_JS_URL", $this->mejs_path.'/build/mediaelement-and-player.js');
        $tpl->setVariable("PLAYER_CSS_URL", $this->mejs_path.'/build/mediaelementplayer.css');
        $tpl->setVariable("FRAME_JS_URL", "js/ilPCLimitedMediaPlayerFrame.js");
        $tpl->setVariable("CONFIG", json_encode($config));

		$tpl->show();
	}


    /**
     * Update the usage data of the currently played medium
     * This is called by ajax
     */
	protected function updateUsage()
    {
        $this->usage->updateUsage($_POST['current_plays'], $_POST['current_seconds']);
        echo json_encode(array(
            'status' => (string) $this->usage->getStatus($this->limit_plays, false),
            'seconds' => (float) $this->usage->getSeconds(),
            'plays' => (int) $this->usage->getPlays()
            )
        );
    }

    /**
     * Update the stored player volume
     * This is called by ajax
     */
    protected function updateVolume()
    {
        $this->usage->updateVolume($_POST['volume']);
        echo json_encode(true);
    }
}
?>