<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

use ilDBInterface;
use ilSession;
use ilObjTest;

class UsageRepo
{
    private const TABLE = 'copg_pgcp_limply_uses';

    private ilDBInterface $db;
    private int $parent_id;
    private int $page_id;
    private int $mob_id;
    private LimitContext $limit_context;

    public function __construct(
        ilDBInterface $db,
        int $parent_id,
        int $page_id,
        int $mob_id,
        LimitContext $limit_context
    ) {
        $this->db = $db;
        $this->parent_id = $parent_id;
        $this->page_id = $page_id;
        $this->mob_id = $mob_id;
        $this->limit_context = $limit_context;
    }

    public function get(int $user_id): Usage
    {
        switch ($this->limit_context->value()) {

            case  LimitContext::SESSION:
                $plays = ilSession::get('limply_plays-'. $this->parent_id.'-'.$this->page_id .'-'.$this->mob_id .'-'.$user_id) ?? 0;
                $seconds = ilSession::get('limply_seconds-'. $this->parent_id.'-'.$this->page_id .'-'.$this->mob_id .'-'.$user_id) ?? null;
                $pass = null;
                $active_id = null;
                break;

            case LimitContext::USER:
            case LimitContext::TESTPASS:
            default:
                $query = "SELECT plays, seconds, pass, active_id FROM {$this::TABLE} 
                WHERE parent_id = %s AND page_id = %s AND mob_id =  %s AND user_id = %s";

                $res = $this->db->queryF($query,
                    ['integer', 'integer', 'integer', 'integer'],
                    [$this->parent_id, $this->page_id, $this->mob_id, $user_id]);

                $row = (array) $this->db->fetchAssoc($res);
                $plays = $row['plays'] ?? 0;
                $seconds = $row['seconds'] ?? null;
                $pass = $row['pass'] ?? null;
                $active_id = $row['active_id'] ?? null;
                break;
        }

        return $this->changeByContext(new Usage($user_id, $this->parent_id, $this->page_id, $this->mob_id,
            (int) $plays,
            isset($seconds) ? (float) $seconds : null,
            isset($pass) ? (int) $pass : null,
            isset($active_id) ? (int) $active_id : null
        ));
    }

    public function save(Usage $usage): void
    {
        $usage = $this->changeByContext($usage);

        switch ($this->limit_context->value()) {

            case LimitContext::SESSION:
                ilSession::set('limply_plays-'. $usage->getParentId().'-'.$usage->getPageId() .'-'.$usage->getMobId() .'-'. $usage->getUserId(),
                    $usage->getPlays()
                );
                ilSession::set('limply_seconds-'. $usage->getParentId().'-'.$usage->getPageId() .'-'.$usage->getMobId() .'-'. $usage->getUserId(),
                    $usage->getPlays()
                );
                break;

            case LimitContext::USER:
            case LimitContext::TESTPASS:
            default:

            $this->db->replace(self::TABLE,
                array(
                    'parent_id' => array('integer', $usage->getParentId()),
                    'page_id' => array('integer', $usage->getPageId()),
                    'mob_id' => array('integer', $usage->getMobId()),
                    'user_id' => array('integer', $usage->getUserId()),
                ),
                array(
                    'plays' => array('integer', $usage->getPlays()),
                    'seconds' => array('float', $usage->getSeconds()),
                    'pass' => array('integer', $usage->getPass()),
                    'active_id' => array('integer', $usage->getActiveId()),
                )
            );
        }
    }



    /**
     * Change the usage if the status of hte context has changed
     * Plays and seconds should be reset if a new test pass has started
     */
    private function changeByContext(Usage $usage): Usage
    {
        if ($this->limit_context->value() == LimitContext::TESTPASS) {
            $test_id = ilObjTest::_getTestIDFromObjectID($usage->getParentId());
            $active_id = ilObjTest::_getActiveIdOfUser($usage->getUserId(), $test_id);
            $pass = ilObjTest::_getPass($active_id);

            if ( $usage->getPass() !== $pass || $usage->getActiveId() !== $active_id) {
                return new Usage($usage->getUserId(), $usage->getParentId(), $usage->getPageId(), $usage->getMobId(),
                    0, null, $pass, isset($active_id) ? (int) $active_id : null
                );
            }
        }
        return $usage;
    }
}