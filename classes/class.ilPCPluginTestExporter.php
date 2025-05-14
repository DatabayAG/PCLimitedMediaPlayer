<?php

declare(strict_types=1);

class ilPCPluginTestExporter extends ilTestExporter
{
    private ilPCPluginExportImportStore $pc_plugin_store;

    public function __construct() {
        parent::__construct();
        $this->pc_plugin_store = ilPCPluginExportImportStore::getInstance();
    }

    public function getXmlRepresentation(string $a_entity, string $a_schema_version, string $id): string
    {
        $tst = new ilObjTest((int) $id, false);
        $tst->read();

        foreach ($tst->getQuestions() as $question_id) {
            $page_object = new ilAssQuestionPage((int) $question_id);
            $page_object->buildDom();
            $this->pc_plugin_store->extractPluginProperties($page_object);
        }

        return parent::getXmlRepresentation($a_entity, $a_schema_version, $id);
    }

    public function getXmlExportTailDependencies(string $a_entity, string $a_target_release, array $a_ids): array
    {
        return array_merge(
            parent::getXmlExportTailDependencies($a_entity, $a_target_release, $a_ids),
            $this->pc_plugin_store->getPluginDependencies()
        );
    }
}