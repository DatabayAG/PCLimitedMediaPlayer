<?php
/**
 * Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

/**
 * Usage log for limited media player
 *
 * @author Fred Neumann <fred.neumann@ili.fau.de>
 * @version $Id$
 */
class ilLimitedMediaPlayerUsage
{
	const CONTEXT_USER = 'user';
	const CONTEXT_SESSION = 'session';
	const CONTEXT_TESTPASS = 'testpass';

	const STATUS_START = 'start';   // playing can be started, button action is 'start'
	const STATUS_PLAY = 'playing';  // playing should be immediately continued, e.g. at page reload when pause is forbidden
	const STATUS_PAUSE = 'pause';   // playing is paused, button action is 'continue'
	const STATUS_LIMIT = 'limit';   // playing limit is reached, button is not shown

	/**
	 * @var integer		count of started media plays
     *                  (each click on 'start' increases this number)
	 */
	private $plays = 0;

    /**
     * @var float		value of played seconds for the current play
     *                  (internally -1 if a new play is not started or the last play has ended regularly)
     */
    private $seconds = -1;

    /**
	 * @var integer		testpass of the counted uses (internally -1 if context is not a testpass or medium is not yet played)
	 */
	private $pass = -1;	


	private $parent_id;
	private $page_id;
	private $mob_id;
	private $user_id;
	private $context;

	/**
	 * Constructor
	 * @param	int		id of the content page
	 * @param	int		id of the media object
	 * @param	int		user id
	 * @param	string	usage context
	 */
	public function __construct($parent_id, $page_id, $mob_id, $user_id, $context = self::CONTEXT_SESSION)
	{
	    global $ilUser;

        $this->parent_id = (int) $parent_id;
		$this->page_id = (int) $page_id;
		$this->mob_id = (int) $mob_id;
		$this->user_id = (int) $user_id;
		$this->context = $context;

		// gest the stored usage
		$this->read();

		// check if the test pass has changed
		if ($this->context == ilLimitedMediaPlayerUsage::CONTEXT_TESTPASS)
        {
            require_once "./Modules/Test/classes/class.ilObjTest.php";
            $test_id = ilObjTest::_getTestIDFromObjectID($this->parent_id);
            $active_id = ilObjTest::_getActiveIdOfUser($ilUser->getId(),$test_id);
            $pass = ilObjTest::_getPass($active_id);

            if($pass != $this->pass)
            {
                $this->pass = $pass;
                $this->plays = 0;
                $this->seconds = -1;
                $this->write();
            }
        }
	}

	
	/**
	 * Get the number of plays for the currently active context
	 * @return	int		number of plays
	 */
	public function getPlays()
	{
        return $this->plays;
	}


    /**
     * Get the value of played seconds for the current play
     * @return	float	number of seconds
     */
    public function getSeconds()
    {
        return $this->seconds;
    }

    /**
     * Get the current status of the player
     *
     * @param int $a_limit_plays
     * @param bool $a_play_pause
     */
    public function getStatus($a_limit_plays = 0, $a_play_pause = true)
    {
        // get the playing status
        if ($a_limit_plays == 0 || $this->plays < $a_limit_plays)
        {
            // no limit is defined or the plays have not reached the limit
            // -1 seconds indicate that the first or the next play can be started
            // otherwise the seconds give the playing position
            $status = ($this->seconds < 0 ? self::STATUS_START : self::STATUS_PLAY);
        }
        elseif (($this->plays == $a_limit_plays))
        {
            // the last last play has been started
            // -1 seconds are set when it is finished => limit reached
            // otherwise the seconds give the playing position
            $status = ($this->seconds < 0 ? self::STATUS_LIMIT : self::STATUS_PLAY);
        }
        else
        {
            // plays have already exceeded the limit (should not happen)
            $status = self::STATUS_LIMIT;
        }

        // adjust the playing status if pause is allowed
        if ($status == self::STATUS_PLAY && $a_play_pause == true)
        {
            $status = self::STATUS_PAUSE;
        }

        return $status;
    }

    /**
     * Handle the viewing of a page
     * @param bool $a_play_pause
     */
    public function handlePageView($a_play_pause = true)
    {
        if ($a_play_pause == false && $this->seconds >= 0)
        {
            $this->seconds = -1;
            $this->write();
        }
    }

    /**
	 * Set the number of plays and seconds for the currently active context
	 * @param	int		number of plays to set
     * @param	int		number of seconds to set
	 */
	public function updateUsage($plays, $seconds)
	{
        $this->plays = (int) $plays;
        $this->seconds = (float) $seconds;
        $this->write();
	}
	
	/**
	 * read data from storage, depending on the context
	 */
	private function read()
	{
		global $ilDB;
		
		if ($this->context == self::CONTEXT_SESSION)
		{
			$plays = $_SESSION['limply_plays-'. $this->parent_id.'-'.$this->page_id .'-'.$this->mob_id .'-'.$this->user_id];
            $seconds = $_SESSION['limply_seconds-'. $this->parent_id.'-'.$this->page_id .'-'.$this->mob_id .'-'.$this->user_id];
			if (isset($plays))
			{
				$this->plays = (int) $plays;
                $this->seconds = (int) $seconds;
			}
		}
		else
		{			
			$query = "SELECT * FROM copg_pgcp_limply_uses "
            . " WHERE parent_id = " . $ilDB->quote($this->parent_id, 'integer')
			. " AND page_id = " . $ilDB->quote($this->page_id, 'integer')
			. " AND mob_id = " . $ilDB->quote($this->mob_id, 'integer')
			. " AND user_id = " . $ilDB->quote($this->user_id, 'integer');
			$res = $ilDB->query($query);

			$row = $ilDB->fetchAssoc($res);
			if ($row)
			{
				$this->plays = (int) $row['plays'];
                $this->seconds = (float) $row['seconds'];
				$this->pass = (int) $row['pass'];
			}
		}
    }
	
	/**
	 * write data to the storage, depending on the context
	 */
	private function write()
	{
		global $ilDB;
		
		if ($this->context == self::CONTEXT_SESSION)
		{
			$_SESSION['limply_plays-'.$this->parent_id .'-'.$this->page_id .'-'.$this->mob_id .'-'.$this->user_id] = (int) $this->plays;
            $_SESSION['limply_seconds-'.$this->parent_id .'-'.$this->page_id .'-'.$this->mob_id .'-'.$this->user_id] = (int) $this->seconds;
		}
		else
		{
			$ilDB->replace('copg_pgcp_limply_uses',
				array(
                    'parent_id' => array('integer', $this->parent_id),
					'page_id' => array('integer', $this->page_id),
					'mob_id' => array('integer', $this->mob_id),
					'user_id' => array('integer', $this->user_id),
				), 
				array(
					'plays' => array('integer', $this->plays),
                    'seconds' => array('float', $this->seconds),
					'pass' => array('integer', $this->pass)
				)
			);	
		}
	}


    /**
     * Get the volume setting
     * @return float $volume
     */

    public function getVolume()
    {
        return isset( $_SESSION['limply_volume']) ?  $_SESSION['limply_volume'] : 0.5;
    }

    /**
     * Update the volume setting
     * @param float $volume
     */
	public function updateVolume($volume)
    {
        $_SESSION['limply_volume'] = $volume;
    }
}
?>