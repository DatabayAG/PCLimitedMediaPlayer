<?php

declare(strict_types=1);

class ilPatchedTestImporter extends ilTestImporter
{
    private ilPatchedCOPageImporter $page_importer;

    public function init(): void
    {
        parent::init();
        $this->page_importer = new ilPatchedCOPageImporter();
        $this->page_importer->setImport($this->getImport());
        $this->page_importer->init();
    }

    public function importXmlRepresentation(string $a_entity, string $a_id, string $a_xml, ilImportMapping $a_mapping): void
    {
        parent::importXmlRepresentation($a_entity, $a_id, $a_xml, $a_mapping);

        foreach ($a_mapping->getMappingsOfEntity('components/ILIAS/Test', 'quest') as $old_id => $new_id) {
            $page_object = new ilAssQuestionPage((int) $new_id);
            $page_object->buildDom();
            $this->page_importer->extractPluginProperties($page_object);

            $a_mapping->addMapping(
                "components/ILIAS/COPage",
                "pg",
                'qpl:' . $old_id,
                'qpl:' . $new_id
            );
        }
    }

    public function finalProcessing(ilImportMapping $a_mapping): void
    {
        $page_map = $a_mapping->getMappingsOfEntity("components/ILIAS/COPage", "pg");
        foreach ($page_map as $new_page_id) {
            $parts = explode(":", $new_page_id);
            $page = ilPageObjectFactory::getInstance($parts[0], (int) $parts[1], 0, '-');
            if ($this->page_importer->replacePluginProperties($page)) {
                $page->update(false, true);
            }
        }

        parent::finalProcessing($a_mapping);
    }
}
