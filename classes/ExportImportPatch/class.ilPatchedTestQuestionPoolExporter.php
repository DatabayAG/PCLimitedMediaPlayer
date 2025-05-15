<?php

declare(strict_types=1);

class ilPatchedTestQuestionPoolExporter extends ilTestQuestionPoolExporter
{
    private ilPatchedCOPageExporter $page_exporter;

    public function init(): void
    {
        parent::init();
        $this->page_exporter = new ilPatchedCOPageExporter();
        $this->page_exporter->setExport($this->getExport());
        $this->page_exporter->init();
    }

    public function getXmlRepresentation(string $a_entity, string $a_schema_version, string $id): string
    {
        $pool = new ilObjQuestionPool((int) $id, false);
        $pool->read();

        foreach ($pool->getAllQuestions() as $question_id) {
            $page_object = new ilAssQuestionPage((int) $question_id);
            $page_object->buildDom();
            $this->page_exporter->extractPluginProperties($page_object);
        }

        return parent::getXmlRepresentation($a_entity, $a_schema_version, $id);
    }

    public function getXmlExportTailDependencies(string $a_entity, string $a_target_release, array $a_ids): array
    {
        return array_merge(
            parent::getXmlExportTailDependencies($a_entity, $a_target_release, $a_ids),
            $this->page_exporter->getPluginDependencies()
        );
    }
}
