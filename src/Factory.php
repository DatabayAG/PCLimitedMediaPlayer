<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

use ilDBInterface;
use ILIAS\ResourceStorage\Services as ResourceStorage;
use ILIAS\UI\Component\Input\Field\UploadHandler;
use ilPCLimitedMediaPlayerUploadHandlerGUI;
use ILIAS\Plugin\LimitedMediaPlayer\StakeholderForUpload;
use ILIAS\Plugin\LimitedMediaPlayer\StakeholderForUse;
use ILIAS\ResourceStorage\Stakeholder\Repository\StakeholderDBRepository;

class Factory
{
    private ilDBInterface $db;
    private ResourceStorage $resource_storage;

    private array $instances = [];

    public function __construct()
    {
        global $DIC;

        $this->db = $DIC->database();
        $this->resource_storage = $DIC->resourceStorage();
    }

    public function mediumRepo(): MediumRepo
    {
        return $this->instances[MediumRepo::class] ?? new MediumRepo(
            $this->db,
            $this->resource_storage,
            new StakeholderDBRepository($this->db),
            new StakeholderForUpload(),
            new StakeholderForUse()
        );
    }

    public function usageRepo(): UsageRepo
    {
        return $this->instances[UsageRepo::class] ?? new UsageRepo($this->db);
    }

    public function limitRepo(): LimitRepo
    {
        return $this->instances[LimitRepo::class] ?? new LimitRepo($this->db);
    }

    public function preferencesRepo(): PreferencesRepo
    {
        return $this->instances[PreferencesRepo::class] = new PreferencesRepo();
    }

    public function uploadHandler(): ilPCLimitedMediaPlayerUploadHandlerGUI
    {
        return $this->instances[ilPCLimitedMediaPlayerUploadHandlerGUI::class] ??
            new ilPCLimitedMediaPlayerUploadHandlerGUI(new StakeholderForUpload());
    }
}
