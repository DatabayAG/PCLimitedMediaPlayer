<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

/**
 * Usage count of a medium by a user
 */
class Usage
{
    private int $user_id;
    private int $parent_id;
    private int $page_id;
    private string $file_id;
    private int $plays;
    private ?float $seconds;
    private ?int $pass;
    private ?int $active_id;

    public function __construct(
        int $user_id,
        int $parent_id,
        int $page_id,
        string $file_id,
        int $plays = 0,
        ?float $seconds = null,
        ?int $pass = null,
        ?int $active_id = null
    ) {
        $this->user_id = $user_id;
        $this->parent_id = $parent_id;
        $this->page_id = $page_id;
        $this->file_id = $file_id;
        $this->plays = $plays;
        $this->seconds = $seconds;
        $this->pass = $pass;
        $this->active_id = $active_id;
    }

    /**
     * ID of the user for which the usage is counted
     */
    public function getUserId(): int
    {
        return $this->user_id;
    }

    /**
     * ID of the pages parent object
     */
    public function getParentId(): int
    {
        return $this->parent_id;
    }

    /**
     * ID of the page object
     */
    public function getPageId(): int
    {
        return $this->page_id;
    }

    /**
     * ID of the file resource
     */
    public function getFileId(): string
    {
        return $this->file_id;
    }

    /**
     * Count of started media replays
     * (each click on 'start' increases this number)
     */
    public function getPlays(): int
    {
        return $this->plays;
    }

    /**
     * Played seconds for the current replays
     * (null if a new replay is not started or the last replay has ended regularly)
     */
    public function getSeconds(): ?float
    {
        return $this->seconds;
    }

    /**
     * Test pass of the counted uses
     * (null if context is not a test pass or medium is not yet replayed)
     */
    public function getPass(): ?int
    {
        return $this->pass;
    }

    /**
     * Active_id from the currently saved passes
     * (null if context is not a test pass or medium is not yet replayed)
     */
    public function getActiveId(): ?int
    {
        return $this->active_id;
    }

    /**
     * Get the current status of the player
     * This is used to initialize the controls when a page is viewed
     */
    public function getStatus(?int $limit_plays, bool $pause_allowed): Status
    {
        if ($limit_plays === null || $this->plays < $limit_plays) {
            // no limit is defined or the plays have not reached the limit
            // null seconds indicate that the first or the next play can be started
            // otherwise the seconds give the playing position
            $status = ($this->seconds === null ? Status::START : Status::PLAY);
        } elseif ($this->plays === $limit_plays) {
            // the last play has been started
            // seconds are set to null when it is finished => limit reached
            // otherwise the seconds give the playing position
            $status = ($this->seconds === null ? Status::LIMIT : Status::PLAY);
        } else {
            // plays have already exceeded the limit (should not happen)
            $status = Status::LIMIT;
        }

        if ($status === Status::PLAY && $pause_allowed) {
            // set to paused if allowed
            // this shows the 'continue' button instead of the 'play' button
            $status = Status::PAUSE;
        }

        return Status::from($status);
    }

    /**
     * Set that the page with the medium is viewed
     * Clear played seconds if pause is not allowed
     */
    public function setPageView(bool $pause_allowed): self
    {
        if (!$pause_allowed) {
            $this->seconds = null;
        }
        return $this;
    }

    /**
     * Set the playing progress
     */
    public function setProgress(int $plays, ?float $seconds): self
    {
        $this->plays = $plays;
        $this->seconds = $seconds;
        return $this;
    }
}
