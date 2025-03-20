<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

use ilDBInterface;
use ilDBConstants;

class LimitRepo
{
    private const TABLE = 'limply_limit';

    private ilDBInterface $db;
    private int $parent_id;

    public function __construct(
        ilDBInterface $db,
        int $parent_id
    ) {
        $this->db = $db;
        $this->parent_id = $parent_id;
    }

    /**
     * Get all limits defined for a parent object
     * @return  Limit[]
     */
    public function all(): array
    {
        $result = $this->db->queryF("SELECT * FROM " . self::TABLE . " WHERE parent_id = %s", ['integer'], [$this->parent_id]);

        $limits = [];
        while ($row = $this->db->fetchAssoc($result)) {
            $limits[] = new Limit($row['id'], $row['parent_id'], $row['page_id'], $row['mob_id'], $row['user_id'], $row['limit_plays']);
        }

        return $limits;
    }

    /**
     * Get the effective limit for a medium and user
     * Use the best fitting adapted limit or the default
     * This respects the priorities among limit settings
     */
    public function effective(Limit $default): Limit
    {
        $query = "SELECT * FROM " . self::TABLE . " WHERE "
            . implode(' AND ', [
                $this->strict('parent_id', ilDBConstants::T_INTEGER, $this->parent_id),
                $this->lax('page_id', ilDBConstants::T_INTEGER, $default->getPageId()),
                $this->lax('file_id', ilDBConstants::T_TEXT, $default->getFileId()),
                $this->lax('user_id', ilDBConstants::T_INTEGER, $default->getUserId())
            ]);

        $result = $this->db->query($query);

        /** @var Limit[] $limits */
        $limits = [$default];
        while ($row = $this->db->fetchAssoc($result)) {
            $limits[] = new Limit($row['id'], $row['parent_id'], $row['page_id'], $row['mob_id'], $row['user_id'], $row['limit_plays'], false);
        }

        usort($limits, fn (Limit $a, Limit $b) => $a->getPriority() <=> $b->getPriority());
        return $limits[0];
    }

    public function delete(Limit $limit): void
    {
        $query = "DELETE FROM " . self::TABLE . " WHERE "
        . implode(' AND ', [
            $this->strict('parent_id', ilDBConstants::T_INTEGER, $limit->getParentId()),
            $this->strict('page_id', ilDBConstants::T_INTEGER, $limit->getPageId()),
            $this->strict('file_id', ilDBConstants::T_TEXT, $limit->getFileId()),
            $this->strict('user_id', ilDBConstants::T_INTEGER, $limit->getUserId())
            ]);

        $this->db->manipulate($query);
    }

    public function save(Limit $limit): void
    {
        $this->db->replace(
            self::TABLE,
            [
                'id' => $limit->getId(),
            ],
            [
                'parent_id' => [ilDBConstants::T_INTEGER, $this->parent_id],
                'page_id' => [ilDBConstants::T_INTEGER, $limit->getPageId()],
                'file_id' => [ilDBConstants::T_TEXT, $limit->getFileId()],
                'user_id' => [ilDBConstants::T_INTEGER, $limit->getUserId()],
                'limit_plays' => [ilDBConstants::T_INTEGER, $limit->getPlays()],
            ]
        );
    }

    /**
     * Compare nullable field strictly
     * NULL value in db fits only if null is given
     */
    private function strict(string $field, string $type, ?int $value): string
    {
        if ($value === null) {
            return "$field IS NULL";
        }
        return "$field=" . $this->db->quote($value, $type);
    }

    /**
     * Compare nullable field laxly
     * NULL value in db fits always
     */
    private function lax(string $field, string $type, $value): string
    {
        if ($value === null) {
            return "$field IS NULL";
        }
        return "($field IS NULL OR $field=" . $this->db->quote($value, $type) . ')';
    }
}
