<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

use ILIAS\ResourceStorage\Stakeholder\AbstractResourceStakeholder;

class StakeholderForUse extends AbstractResourceStakeholder
{
    public function __construct()
    {
    }

    public function getId(): string
    {
        return 'PCLimitedMediaPlayerUse';
    }

    public function getOwnerOfNewResources(): int
    {
        return SYSTEM_USER_ID;
    }

}
