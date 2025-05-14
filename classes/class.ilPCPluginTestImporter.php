<?php

declare(strict_types=1);

class ilPCPluginTestImporter extends ilTestImporter
{
    private ilPCPluginExportImportStore $pc_plugin_store;

    public function __construct() {
        parent::__construct();
        $this->pc_plugin_store = ilPCPluginExportImportStore::getInstance();
    }

    public function addTexonomyAndQuestionsMapping(array $question_id_mapping, int $new_obj_id, ilImportMapping $mapping): ilImportMapping
    {
        $mapping = parent::addTexonomyAndQuestionsMapping($question_id_mapping, $new_obj_id, $mapping);

        foreach ($question_id_mapping as $oldQuestionId => $newQuestionId) {
            // needed for the import of page component plugins
            $mapping->addMapping(
                "Services/COPage",
                "pg",
                'qpl:' . $oldQuestionId,
                'qpl:' . $newQuestionId
            );
        }
        return $mapping;
    }

    public function finalProcessing(ilImportMapping $a_mapping): void
    {
        $page_map = $a_mapping->getMappingsOfEntity("Services/COPage", "pg");
        foreach ($page_map as $new_page_id) {
            $parts = explode(":", $new_page_id);
            $page = ilPageObjectFactory::getInstance($parts[0], (int) $parts[1], 0, '-');
            if ($this->pc_plugin_store->replacePluginProperties($page)) {
                $page->update(false, true);
            }
        }

        parent::finalProcessing($a_mapping);
    }
}