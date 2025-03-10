<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

use ilDBInterface;
use ilObjUser;

class Factory
{
    private ilDBInterface $db;

    private array $instances = [];

    public function __construct(ilDBInterface $db)
    {
        $this->db = $db;
    }

    public function usageRepo(
        int $parent_id,
        int $page_id,
        int $mob_id,
        LimitContext $limit_context
    ) {
        return $this->instances[UsageRepo::class][$parent_id][$page_id][$mob_id][$limit_context->value()] ??
            new UsageRepo($this->db, $parent_id, $page_id, $mob_id, $limit_context);
    }

    public function LimitRepo(
        int $parent_id
    ) {
        return $this->instances[LimitRepo::class][$parent_id] ??
            new LimitRepo($this->db, $parent_id);
    }

    public function preferencesRepo()
    {
        return $this->instances[PreferencesRepo::class] = new PreferencesRepo();
    }
}
