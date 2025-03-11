<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

class Medium
{
    private int $page_id;
    private int $mob_id;
    private string $title;
    private int $limit;

    public function __construct(
        int $page_id,
        int $mob_id,
        string $title,
        int $limit
    ) {
        $this->page_id = $page_id;
        $this->mob_id = $mob_id;
        $this->title = $title;
        $this->limit = $limit;
    }

    public function getPageId(): int
    {
        return $this->page_id;
    }

    public function getMobId(): int
    {
        return $this->mob_id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }
}
