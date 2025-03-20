<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

class Medium
{
    private int $page_id;
    private string $file_id;
    private ?string $preview_id;
    private string $title;
    private ?int $limit_plays;
    private LimitContext $limit_context;
    private ?int $width;
    private ?int $height;
    private bool $play_in_modal;
    private bool $play_with_pause;

    public function __construct(
        int $page_id,
        string $file_id,
        ?string $preview_id,
        string $title,
        ?int $limit_plays,
        LimitContext $limit_context,
        ?int $width,
        ?int $height,
        bool $play_in_modal,
        bool $play_with_pause
    ) {
        $this->page_id = $page_id;
        $this->file_id = $file_id;
        $this->preview_id = $preview_id;
        $this->title = $title;
        $this->limit_plays = $limit_plays;
        $this->limit_context = $limit_context;
        $this->width = $width;
        $this->height = $height;
        $this->play_in_modal = $play_in_modal;
        $this->play_with_pause = $play_with_pause;
    }

    public static function fromProperties(int $page_id, array $properties): Medium
    {
        return new self(
            $page_id,
            empty($properties['file_id']) ? '' : (string) $properties['file_id'],
            empty($properties['preview_id']) ? null : (string) $properties['preview_id'],
            empty($properties['title']) ? '' : (string) $properties['title'],
            empty($properties['limit_plays']) ? null : (int) $properties['limit_plays'],
            LimitContext::tryFrom($properties['limit_context'] ?? '') ?? LimitContext::from(LimitContext::TESTPASS),
            empty($properties['width']) ? null : (int) $properties['width'],
            empty($properties['height']) ? null : (int) $properties['height'],
            empty($properties['play_in_modal']) ? false : (bool) $properties['play_in_modal'],
            empty($properties['play_with_pause']) ? false : (bool) $properties['play_with_pause']
        );
    }

    public function toProperties(): array
    {
        return [
          'file_id' => $this->file_id,
          'preview_id' => (string) $this->preview_id,
          'title' => $this->title,
          'limit_plays' => (string) $this->limit_plays,
          'limit_context' => $this->limit_context->value(),
          'width' => (string) $this->width,
          'height' => (string) $this->height,
          'play_in_modal' => (string) $this->play_in_modal,
          'play_with_pause' => (string) $this->play_with_pause,
        ];
    }


    public function getPageId(): int
    {
        return $this->page_id;
    }

    public function setPageId(int $page_id): Medium
    {
        $this->page_id = $page_id;
        return $this;
    }

    public function getFileId(): string
    {
        return $this->file_id;
    }

    public function setFileId(string $file_id): Medium
    {
        $this->file_id = $file_id;
        return $this;
    }

    public function getPreviewId(): ?string
    {
        return $this->preview_id;
    }

    public function setPreviewId(?string $preview_id): Medium
    {
        $this->preview_id = $preview_id;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setMediumTitle(string $title): Medium
    {
        $this->title = $title;
        return $this;
    }

    public function getLimitPlays(): ?int
    {
        return $this->limit_plays;
    }

    public function setLimitPlays(?int $limit_plays): Medium
    {
        $this->limit_plays = $limit_plays;
        return $this;
    }

    public function getLimitContext(): LimitContext
    {
        return $this->limit_context;
    }

    public function setLimitContext(LimitContext $limit_context): Medium
    {
        $this->limit_context = $limit_context;
        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): Medium
    {
        $this->width = $width;
        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): Medium
    {
        $this->height = $height;
        return $this;
    }

    public function getPlayInModal(): bool
    {
        return $this->play_in_modal;
    }

    public function setPlayInModal(bool $play_in_modal): Medium
    {
        $this->play_in_modal = $play_in_modal;
        return $this;
    }

    public function getPlayWithPause(): bool
    {
        return $this->play_with_pause;
    }

    public function setPlayWithPause(bool $play_with_pause): Medium
    {
        $this->play_with_pause = $play_with_pause;
        return $this;
    }
}
