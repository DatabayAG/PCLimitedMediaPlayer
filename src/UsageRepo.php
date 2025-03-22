<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

use ilDBInterface;
use ilSession;
use ilObjTest;
use ilDBConstants;

class UsageRepo
{
    private const TABLE = 'limply_uses';

    private ilDBInterface $db;

    public function __construct(
        ilDBInterface $db
    ) {
        $this->db = $db;
    }

    public function get(int $user_id, int $parent_id, int $page_id, string $file_id, LimitContext $context): Usage
    {
        switch ($context->value()) {

            case LimitContext::SESSION:
                $plays = ilSession::get('limply_plays-' . $parent_id . '-' . $page_id . '-' . $file_id . '-' . $user_id) ?? 0;
                $seconds = ilSession::get('limply_seconds-' . $parent_id . '-' . $page_id . '-' . $file_id . '-' . $user_id) ?? null;
                $pass = null;
                $active_id = null;
                break;

            case LimitContext::USER:
            case LimitContext::TESTPASS:
            default:
                $query = "SELECT plays, seconds, pass, active_id FROM " . self::TABLE . "
                WHERE parent_id = %s AND page_id = %s AND file_id =  %s AND user_id = %s";

                $res = $this->db->queryF(
                    $query,
                    [ilDBConstants::T_INTEGER, ilDBConstants::T_INTEGER, ilDBConstants::T_TEXT, ilDBConstants::T_INTEGER],
                    [$parent_id, $page_id, $file_id, $user_id]
                );

                $row = (array) $this->db->fetchAssoc($res);
                $plays = $row['plays'] ?? 0;
                $seconds = $row['seconds'] ?? null;
                $pass = $row['pass'] ?? null;
                $active_id = $row['active_id'] ?? null;
                break;
        }

        return $this->changeByContext(new Usage(
            $user_id,
            $parent_id,
            $page_id,
            $file_id,
            (int) $plays,
            isset($seconds) ? (float) $seconds : null,
            isset($pass) ? (int) $pass : null,
            isset($active_id) ? (int) $active_id : null
        ), $context);
    }

    public function save(Usage $usage, LimitContext $context): void
    {
        $usage = $this->changeByContext($usage, $context);

        switch ($context->value()) {

            case LimitContext::SESSION:
                ilSession::set(
                    'limply_plays-' . $usage->getParentId() . '-' . $usage->getPageId() . '-' . $usage->getFileId() . '-' . $usage->getUserId(),
                    $usage->getPlays()
                );
                ilSession::set(
                    'limply_seconds-' . $usage->getParentId() . '-' . $usage->getPageId() . '-' . $usage->getFileId() . '-' . $usage->getUserId(),
                    $usage->getPlays()
                );
                break;

            case LimitContext::USER:
            case LimitContext::TESTPASS:
            default:

                $this->db->replace(
                    self::TABLE,
                    array(
                        'parent_id' => array(ilDBConstants::T_INTEGER, $usage->getParentId()),
                        'page_id' => array(ilDBConstants::T_INTEGER, $usage->getPageId()),
                        'file_id' => array(ilDBConstants::T_TEXT, $usage->getFileId()),
                        'user_id' => array(ilDBConstants::T_INTEGER, $usage->getUserId()),
                    ),
                    array(
                        'plays' => array(ilDBConstants::T_INTEGER, $usage->getPlays()),
                        'seconds' => array(ilDBConstants::T_FLOAT, $usage->getSeconds()),
                        'pass' => array(ilDBConstants::T_INTEGER, $usage->getPass()),
                        'active_id' => array(ilDBConstants::T_INTEGER, $usage->getActiveId()),
                    )
                );
        }
    }

    /**
     * Change the usage if the status of the context has changed
     * Plays and seconds should be reset if a new test pass has started
     */
    private function changeByContext(Usage $usage, LimitContext $context): Usage
    {
        if ($context->value() == LimitContext::TESTPASS) {
            $test_id = ilObjTest::_getTestIDFromObjectID($usage->getParentId());
            $active_id = ilObjTest::_getActiveIdOfUser($usage->getUserId(), $test_id);
            $pass = ilObjTest::_getPass($active_id);

            if ($usage->getPass() !== $pass || $usage->getActiveId() !== $active_id) {
                return new Usage(
                    $usage->getUserId(),
                    $usage->getParentId(),
                    $usage->getPageId(),
                    $usage->getFileId(),
                    0,
                    null,
                    $pass,
                    isset($active_id) ? (int) $active_id : null
                );
            }
        }
        return $usage;
    }
}
