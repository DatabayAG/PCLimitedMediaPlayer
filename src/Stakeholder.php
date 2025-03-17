<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

use ILIAS\ResourceStorage\Stakeholder\AbstractResourceStakeholder;

class Stakeholder extends AbstractResourceStakeholder
{
    public function getId(): string
    {
        return 'PCLimitedMediaPlayer';
    }

    public function getOwnerOfNewResources(): int
    {
        return SYSTEM_USER_ID;
    }
}