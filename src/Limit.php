<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

/**
 * Playing limit setting
 * Default limits are stored in the page content
 * Adaptation are stored in a database table
 */
class Limit
{
    private ?int $id;
    private int $parent_id;
    private ?int $page_id;
    private ?string $file_id;
    private ?int $user_id;
    private ?int $plays;
    private bool $default = false;

    public function __construct(?int $id, int $parent_id, ?int $page_id, ?string $file_id, ?int $user_id, ?int $plays, bool $default = false)
    {
        $this->id = $id;
        $this->parent_id = $parent_id;
        $this->page_id = $page_id;
        $this->file_id = $file_id;
        $this->user_id = $user_id;
        $this->plays = $plays;
        $this->default = $default;
    }

    public static function fromRow(array $row): Limit
    {
        return new Limit(
            (int) $row['id'],
            (int) $row['parent_id'],
            isset($row['page_id']) ? (int) $row['page_id'] : null,
            isset($row['file_id']) ? (string) $row['file_id'] : null,
            isset($row['user_id']) ? (int) $row['user_id'] : null,
            isset($row['plays']) ? (int) $row['plays'] : null
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id)
    {
        $this->id = $id;
    }

    public function getParentId(): int
    {
        return $this->parent_id;
    }

    public function getPageId(): ?int
    {
        return $this->page_id;
    }

    public function getFileId(): ?string
    {
        return $this->file_id;
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function getPlays(): ?int
    {
        return $this->plays;
    }

    public function setPlays(?int $plays): self
    {
        $this->plays = $plays;
        return $this;
    }

    public function isDefault(): bool
    {
        return $this->default;
    }

    /**
     * Get the priority of this limit (lower number means higher priority)
     */
    public function getPriority(): int
    {
        if ($this->isDefault()) {
            return 4;
        }

        switch (true) {
            case $this->user_id !== null && $this->file_id !== null:
                return 0;   // one user one medium

            case $this->user_id !== null && $this->file_id === null:
                return 1;   // one user all media

            case $this->user_id === null && $this->file_id !== null:
                return 2;   // all user one medium

            case $this->user_id === null && $this->file_id === null:
            default:
                return 3;   // all user all media
        }
    }

    /**
     * Get the medium key for selection in the control plugin
     */
    public function getMediumKey(): ?string
    {
        if ($this->page_id !== null && $this->file_id !== null) {
            return $this->page_id . '_' . $this->file_id;
        }
        return null;
    }
}
