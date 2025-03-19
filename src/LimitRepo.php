<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

use ilDBInterface;

class LimitRepo
{
    private const TABLE = 'copg_pgcp_limply_limit';

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
        $result = $this->db->queryF("SELECT * FROM copg_pgcp_limply_limit WHERE parent_id = %s", ['integer'], [$this->parent_id]);

        $limits = [];
        while ($row = $this->db->fetchAssoc($result)) {
            $limits[] = new Limit($row['parent_id'], $row['page_id'], $row['mob_id'], $row['user_id'], $row['limit_plays']);
        }

        return $limits;
    }

    /**
     * Get the effective limit for a medium and user
     * Use the best fitting adapted limit or the default
     */
    public function effective(Limit $default): Limit
    {
        $query = "SELECT * FROM " . self::TABLE . " WHERE "
            . implode(' AND ', [
                $this->strict('parent_id', $this->parent_id),
                $this->lax('page_id', $default->getPageId()),
                $this->lax('file_id', $default->getFileId()),
                $this->lax('user_id', $default->getUserId())
            ]);

        $result = $this->db->query($query);

        /** @var Limit[] $limits */
        $limits = [$default];
        while ($row = $this->db->fetchAssoc($result)) {
            $limits[] = new Limit($row['parent_id'], $row['page_id'], $row['mob_id'], $row['user_id'], $row['limit_plays'], false);
        }

        usort($limits, fn (Limit $a, Limit $b) => $a->getPriority() <=> $b->getPriority());
        return $limits[0];
    }


    public function delete(Limit $limit): void
    {
        $query = "DELETE FROM " . self::TABLE . " WHERE "
        . implode(' AND ', [
            $this->strict('parent_id', $limit->getParentId()),
            $this->strict('page_id', $limit->getPageId()),
            $this->strict('mob_id', $limit->getMobId()),
            $this->strict('user_id', $limit->getUserId())
            ]);

        $this->db->manipulate($query);
    }

    public function save(Limit $limit): void
    {
        $this->db->replace(
            self::TABLE,
            [
                'parent_id' => ['integer', $this->parent_id],
                'page_id' => ['integer', $limit->getPageId()],
                'mob_id' => ['integer', $limit->getMobId()],
                'user_id' => ['integer', $limit->getUserId()],
            ],
            [
                'limit_plays' => ['integer', $limit->getLimit()],
            ]
        );
    }

    /**
     * Compare nullable integer field strictly
     * NULL value in db fits only if null is given
     */
    private function strict(string $field, ?int $value): string
    {
        if ($field === null) {
            return "$field IS NULL";
        }
        return "$field=" . $this->db->quote($value, 'integer');
    }

    /**
     * Compare nullable integer field laxly
     * NULL value in db fits always
     */
    private function lax(string $field, ?int $value): string
    {
        if ($field === null) {
            return "$field IS NULL";
        }
        return "($field IS NULL OR $field=" . $this->db->quote($value, 'integer') . ')';
    }
}
