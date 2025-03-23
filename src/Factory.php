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
use ILIAS\HTTP\GlobalHttpState;
use ILIAS\Refinery\Factory as Refinery;

class Factory
{
    private ilDBInterface $db;
    private GlobalHttpState $http;
    private ResourceStorage $resource_storage;

    private array $instances = [];
    private Refinery $refinery;

    public function __construct()
    {
        global $DIC;

        $this->db = $DIC->database();
        $this->http = $DIC->http();
        $this->resource_storage = $DIC->resourceStorage();
        $this->refinery = $DIC->refinery();
    }

    public function mediumDelivery(): MediumDelivery
    {
        return $this->instances[MediumDelivery::class] ??=
            new MediumDelivery($this->http);
    }

    public function mediumRepo(): MediumRepo
    {
        return $this->instances[MediumRepo::class] ??= new MediumRepo(
            $this->db,
            $this->resource_storage,
            new StakeholderDBRepository($this->db),
            new StakeholderForUpload(),
            new StakeholderForUse()
        );
    }

    public function usageRepo(): UsageRepo
    {
        return $this->instances[UsageRepo::class] ??= new UsageRepo($this->db);
    }

    public function limitRepo(): LimitRepo
    {
        return $this->instances[LimitRepo::class] ??= new LimitRepo($this->db);
    }

    public function preferencesRepo(): PreferencesRepo
    {
        return $this->instances[PreferencesRepo::class] ??= new PreferencesRepo();
    }

    public function getVariables() : RequestVariables
    {
        return $this->instances[PreferencesRepo::class]['get'] ??=
            new RequestVariables($this->http->wrapper()->query(), $this->refinery);
    }

    public function postVariables() : RequestVariables
    {
        return $this->instances[PreferencesRepo::class]['post'] ??=
            new RequestVariables($this->http->wrapper()->post(), $this->refinery);
    }

    public function uploadHandler(): ilPCLimitedMediaPlayerUploadHandlerGUI
    {
        return $this->instances[ilPCLimitedMediaPlayerUploadHandlerGUI::class] ??=
            new ilPCLimitedMediaPlayerUploadHandlerGUI(new StakeholderForUpload());
    }
}
