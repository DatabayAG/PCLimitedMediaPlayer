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
                $plays = ilSession::get(
                    'limply_plays-' . $parent_id . '-' . $page_id . '-' . $file_id . '-' . $user_id
                ) ?? 0;
                $seconds = ilSession::get(
                    'limply_seconds-' . $parent_id . '-' . $page_id . '-' . $file_id . '-' . $user_id
                ) ?? null;

                return new Usage(
                    $user_id, $parent_id, $page_id, $file_id,
                    (int) $plays,
                    isset($seconds) ? (float) $seconds : null,
                );
                break;

            case LimitContext::USER:
            case LimitContext::TESTPASS:
            default:
                $query = "SELECT plays, seconds, pass, active_id FROM " . self::TABLE . "
                WHERE parent_id = %s AND page_id = %s AND file_id =  %s AND user_id = %s";

                $res = $this->db->queryF(
                    $query,
                    [
                        ilDBConstants::T_INTEGER,
                        ilDBConstants::T_INTEGER,
                        ilDBConstants::T_TEXT,
                        ilDBConstants::T_INTEGER
                    ],
                    [$parent_id, $page_id, $file_id, $user_id]
                );

                $row = (array) $this->db->fetchAssoc($res);
                $usage = new Usage(
                    $user_id, $parent_id, $page_id, $file_id,
                    (int) ($row['plays'] ?? 0),
                    isset($row['seconds']) ? (float) $row['seconds'] : null,
                    isset($row['pass']) ? (int) $row['pass'] : null,
                    isset($row['active_id']) ? (int) $row['active_id'] : null
                );

                // adjust usage if test pass or active id has changed
                if ($context->value() == LimitContext::TESTPASS) {
                    $test_id = (int) ilObjTest::_getTestIDFromObjectID($parent_id ?? 0);
                    $active_id = (int) ilObjTest::_getActiveIdOfUser($user_id, $test_id);
                    $pass = ilObjTest::_getPass($active_id);

                    if ($usage->getPass() !== $pass || $usage->getActiveId() !== $active_id) {
                        $usage = new Usage(
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
                    $this->save($usage, $context);
                }
                return $usage;
        }
    }

    public function save(Usage $usage, LimitContext $context): void
    {
        switch ($context->value()) {

            case LimitContext::SESSION:
                ilSession::set(
                    'limply_plays-' . $usage->getParentId() . '-' . $usage->getPageId() . '-' . $usage->getFileId() . '-' . $usage->getUserId(),
                    $usage->getPlays()
                );
                ilSession::set(
                    'limply_seconds-' . $usage->getParentId() . '-' . $usage->getPageId() . '-' . $usage->getFileId() . '-' . $usage->getUserId(),
                    $usage->getSeconds()
                );
                break;

            case LimitContext::USER:
            case LimitContext::TESTPASS:
            default:

                $this->db->replace(
                    self::TABLE,
                    [
                        'parent_id' => [ilDBConstants::T_INTEGER, $usage->getParentId()],
                        'page_id' => [ilDBConstants::T_INTEGER, $usage->getPageId()],
                        'file_id' => [ilDBConstants::T_TEXT, $usage->getFileId()],
                        'user_id' => [ilDBConstants::T_INTEGER, $usage->getUserId()],
                    ],
                    [
                        'plays' => [ilDBConstants::T_INTEGER, $usage->getPlays()],
                        'seconds' => [ilDBConstants::T_FLOAT, $usage->getSeconds()],
                        'pass' => [ilDBConstants::T_INTEGER, $usage->getPass()],
                        'active_id' => [ilDBConstants::T_INTEGER, $usage->getActiveId()],
                    ]
                );
        }
    }
}
