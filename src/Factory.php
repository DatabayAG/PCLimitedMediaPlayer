<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

use ilDBInterface;
use ilObjUser;
use ilTestSequence;
use ilTestSequenceFactory;
use ilTestSessionFactory;
use ilObjTest;
use \ILIAS\Refinery\Factory as Refinery;
use ilComponentRepository;
use ilLanguage;
use ilTestSession;

class Factory
{
    private ilDBInterface $db;
    private ilObjUser $user;
    private Refinery $refinery;
    private ilLanguage $lng;
    private ilComponentRepository $component_repository;
    private array $instances = [];

    public function __construct()
    {
        global $DIC;

        $this->db = $DIC->database();
        $this->user = $DIC->user();
        $this->lng = $DIC->language();
        $this->refinery = $DIC->refinery();
        $this->component_repository = $DIC['component.repository'];
    }

    public function usageRepo(
        int $parent_id,
        int $page_id,
        int $mob_id,
        LimitContext $limit_context
    ): UsageRepo
    {
        return $this->instances[UsageRepo::class][$parent_id][$page_id][$mob_id][$limit_context->value()] ??
            new UsageRepo($this->db, $parent_id, $page_id, $mob_id, $limit_context);
    }

    public function LimitRepo(int $parent_id): LimitRepo
    {
        return $this->instances[LimitRepo::class][$parent_id] ??
            new LimitRepo($this->db, $parent_id);
    }

    public function preferencesRepo(): PreferencesRepo
    {
        return $this->instances[PreferencesRepo::class] = new PreferencesRepo();
    }

    public function testSequence(int $ref_id): ilTestSequence
    {
        $test_obj = new ilObjTest($ref_id);
        $session_factory = new ilTestSessionFactory($test_obj);
        $session_obj = $session_factory->getSessionByUserId($this->user->getId());

        $sequence_factory = new ilTestSequenceFactory($this->db, $this->lng, $this->refinery, $this->component_repository, $test_obj);
        $sequence_obj = $sequence_factory->getSequenceByTestSession($session_obj);
        $sequence_obj->loadFromDb();
        $sequence_obj->loadQuestions();
        return $sequence_obj;
    }
}
