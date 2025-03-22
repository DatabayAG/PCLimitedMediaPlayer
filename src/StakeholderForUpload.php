<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

use ILIAS\ResourceStorage\Stakeholder\AbstractResourceStakeholder;

class StakeholderForUpload extends AbstractResourceStakeholder
{
    public function getId(): string
    {
        return 'PCLimitedMediaPlayerUpload';
    }

    public function getOwnerOfNewResources(): int
    {
        return SYSTEM_USER_ID;
    }
}
