<?php

declare(strict_types=1);

use ILIAS\Filesystem\Util\LegacyPathHelper;
use ILIAS\Plugin\LimitedMediaPlayer\MediumRepo;

class ilPCLimitedMediaPlayerExporter extends ilPageComponentPluginExporter
{
    private ilPCLimitedMediaPlayerPlugin $plugin;
    private MediumRepo $medium_repo;
    private ilXMLWriter $xml_writer;

    public function init() : void
    {
        echo "init";
        exit;

        global $DIC;
        $this->plugin = $DIC["component.factory"]->getPlugin(ilPCLimitedMediaPlayerPlugin::ID);
        $this->medium_repo = $this->plugin->factory()->mediumRepo();
        $this->xml_writer = new ilXMLWriter();
    }

    public function getXmlExportHeadDependencies(string $a_entity, string $a_target_release, array $a_ids) : array
    {
        echo "getXmlExportHeadDependencies";
        exit;

        $file_ids =[];
        foreach ($a_ids as $id) {
            $properties = self::getPCProperties($id);
            if (!empty($properties['file_id'])) {
                $file_ids[] = $properties['file_id'];
            }
            if (!empty($properties['preview_id'])) {
                $file_ids[] = $properties['preview_id'];
            }
        }

        $export_fs = LegacyPathHelper::deriveFilesystemFrom($this->getAbsoluteExportDirectory());
        $export_path = LegacyPathHelper::createRelativePath($this->getAbsoluteExportDirectory());
        $files_path = $export_path . '/Files';
        $export_fs->createDir($files_path);

        foreach (array_unique($file_ids) as $file_id) {
            $stream = $this->medium_repo->getFileStream($file_id);
            $export_fs->writeStream($files_path . '/' . $file_id, $this->medium_repo->getFileStream($file_id));
        }

        return [];
    }

    public function getXmlRepresentation(string $a_entity, string $a_schema_version, string $a_id) : string
    {
        echo "getXmlRepresentation";
        exit;

        $this->xml_writer->xmlStartTag('PCLimitedMediaPlayer');
        foreach (self::getPCProperties($a_id) as $key => $value) {
            $this->xml_writer->xmlElement($this->toTag($key), null, (string) $value);
        }

        if(!empty($properties['file_id'])) {
            $this->xml_writer->xmlElement('FileName',
            $this->medium_repo->getFileName((string)$properties['file_id']) ?? '');
        }
        if(!empty($properties['preview_id'])) {
            $this->xml_writer->xmlElement('PreviewName',
                $this->medium_repo->getFileName((string)$properties['preview_id']) ?? '');
        }

        $this->xml_writer->xmlEndTag("LongEssayAssessment");
        return $this->xml_writer->xmlDumpMem(false);
    }


    public function getXmlExportTailDependencies(string $a_entity, string $a_target_release, array $a_ids) : array
    {
        echo "getXmlExportTailDependencies";
        exit;
        return [];
    }

    public function getValidSchemaVersions(string $a_entity) : array
    {
        return [
            '5.3.0' => [
                'namespace' => 'http://www.ilias.de/',
                //'xsd_file'     => 'pctpc_5_3.xsd',
                'uses_dataset' => false,
                'min' => '5.3.0',
                'max' => ''
            ]
        ];
    }

    private function toTag(string $name) : string
    {
        return str_replace('_', '', ucwords($name, '_'));
    }
}