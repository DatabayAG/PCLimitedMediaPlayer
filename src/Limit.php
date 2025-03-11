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
    private int $parent_id;
    private ?int $page_id;
    private ?int $mob_id;
    private ?int $user_id;
    private ?int $limit;
    private bool $default = false;

    public function __construct(int $parent_id, ?int $page_id, ?int $mob_id, ?int $user_id, ?int $limit, bool $default = false)
    {
        $this->parent_id = $parent_id;
        $this->page_id = $page_id;
        $this->mob_id = $mob_id;
        $this->user_id = $user_id;
        $this->limit = $limit;
        $this->default = $default;
    }

    public function getParentId(): int
    {
        return $this->parent_id;
    }

    public function getPageId(): ?int
    {
        return $this->page_id;
    }

    public function getMobId(): ?int
    {
        return $this->mob_id;
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function setLimit(?int $limit): self
    {
        $this->limit = $limit;
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
            case $this->user_id !== null && $this->mob_id !== null:
                return 0;   // one user one medium

            case $this->user_id !== null && $this->mob_id === null:
                return 1;   // one user all media

            case $this->user_id === null && $this->mob_id !== null:
                return 2;   // all user one medium

            case $this->user_id === null && $this->mob_id === null:
            default:
                return 3;   // all user all media
        }
    }
}
