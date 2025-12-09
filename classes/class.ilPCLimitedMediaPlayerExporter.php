<?php

declare(strict_types=1);

use ILIAS\Filesystem\Util\LegacyPathHelper;
use ILIAS\Plugin\LimitedMediaPlayer\MediumRepo;

class ilPCLimitedMediaPlayerExporter extends ilPageComponentPluginExporter
{
    private ilPCLimitedMediaPlayerPlugin $plugin;
    private MediumRepo $medium_repo;
    private static $file_ids = [];

    public function init(): void
    {
        global $DIC;
        $this->plugin = $DIC["component.factory"]->getPlugin(ilPCLimitedMediaPlayerPlugin::ID);
        $this->medium_repo = $this->plugin->factory()->mediumRepo();
    }

    public function getXmlExportHeadDependencies(string $a_entity, string $a_target_release, array $a_ids): array
    {
        return [];
    }

    public function getXmlRepresentation(string $a_entity, string $a_schema_version, string $a_id): string
    {
        $properties = self::getPCProperties($a_id);

        $data = [];
        if (!empty($properties['file_id'])) {
            $data['file_name'] = $this->medium_repo->getFileName((string) $properties['file_id']) ?? '';
        }
        if (!empty($properties['preview_id'])) {
            $data['preview_name'] = $this->medium_repo->getFileName((string) $properties['preview_id']) ?? '';
        }

        return '<data>' . htmlentities(json_encode($data)) . '</data>';
    }


    public function getXmlExportTailDependencies(string $a_entity, string $a_target_release, array $a_ids): array
    {
        $file_ids = [];
        foreach ($a_ids as $id) {
            $properties = self::getPCProperties($id);
            if (!empty($properties['file_id'])) {
                $file_ids[] = $properties['file_id'];
            }
            if (!empty($properties['preview_id'])) {
                $file_ids[] = $properties['preview_id'];
            }
        }
        self::$file_ids = $file_ids;

        return [];
    }

    public function getValidSchemaVersions(string $a_entity): array
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

    public function exportFiles(string $export_directory): array
    {
        $export_fs = LegacyPathHelper::deriveFilesystemFrom($export_directory);
        $export_path = LegacyPathHelper::createRelativePath($export_directory);
        $files_path = $export_path . '/PCLimitedMediaFiles';
        $export_fs->createDir($files_path);

        foreach (array_unique(self::$file_ids) as $file_id) {
            $stream = $this->medium_repo->getFileStream($file_id);
            $export_fs->writeStream($files_path . '/' . $file_id, $this->medium_repo->getFileStream($file_id));
        }
        return [];
    }
}
