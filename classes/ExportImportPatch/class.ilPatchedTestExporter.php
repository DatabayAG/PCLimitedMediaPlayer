<?php

declare(strict_types=1);

class ilPatchedTestExporter extends ilTestExporter
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
        // since ilias 10 this is called after the tail dependencies
        // the needed file ids are statically saved in \ilPCLimitedMediaPlayerExporter::getXmlExportTailDependencies
        // exportFiles must be called here because only here the export_run_dir is available

        $exporter = new ilPCLimitedMediaPlayerExporter();
        $exporter->init();
        $exporter->exportFiles($this->exp->export_run_dir);

        return parent::getXmlRepresentation($a_entity, $a_schema_version, $id);
    }

    public function getXmlExportTailDependencies(string $a_entity, string $a_target_release, array $a_ids): array
    {
        // since ILIAS 10 this function is called before getXmlRepresentation
        // page component data of the plugin has to be exported here

        foreach ($a_ids as $id) {
            $tst = new ilObjTest((int) $id, false);
            $tst->read();

            foreach ($tst->getQuestions() as $question_id) {
                $page_object = new ilAssQuestionPage((int) $question_id);
                $page_object->buildDom();
                // this makes the properties of the page components available to ilPCLimitedMediaPayerExporter
                $this->page_exporter->extractPluginProperties($page_object);
            }
        }

        return array_merge(
            parent::getXmlExportTailDependencies($a_entity, $a_target_release, $a_ids),
            // this will call ilPCLimitedMediaPayerExporter as a dependency
            $this->page_exporter->getPluginDependencies()
        );
    }
}
