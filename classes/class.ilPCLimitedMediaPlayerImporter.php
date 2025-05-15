<?php

declare(strict_types=1);

use ILIAS\Plugin\LimitedMediaPlayer\MediumRepo;
use ILIAS\Filesystem\Util\LegacyPathHelper;

class ilPCLimitedMediaPlayerImporter extends ilPageComponentPluginImporter
{
    private ilPCLimitedMediaPlayerPlugin $plugin;
    private MediumRepo $medium_repo;

    public function init(): void
    {
        global $DIC;
        $this->plugin = $DIC["component.factory"]->getPlugin(ilPCLimitedMediaPlayerPlugin::ID);
        $this->medium_repo = $this->plugin->factory()->mediumRepo();
    }

    public function importXmlRepresentation(
        string $a_entity,
        string $a_id,
        string $a_xml,
        ilImportMapping $a_mapping
    ): void {

        $import_directory = $this->getImportDirectory();
        $import_fs = LegacyPathHelper::deriveFilesystemFrom($this->getImportDirectory());
        $import_path = LegacyPathHelper::createRelativePath($this->getImportDirectory());
        $files_path = $import_path . '/PCLimitedMediaFiles';

        $new_id = self::getPCMapping($a_id, $a_mapping);
        $properties = self::getPCProperties($new_id);
        $version = self::getPCVersion($new_id);

        // get the data that is separately exported
        // most properties are on the page
        $data = (array) json_decode(html_entity_decode(substr($a_xml, 6, -7)));

        if (!empty($properties['file_id']) && $import_fs->has($files_path . '/' . $properties['file_id'])) {
            $properties['file_id'] = $this->medium_repo->addFileFromStream(
                $import_fs->readStream($files_path . '/' . $properties['file_id']),
                $data['file_name'] ?? ''
            );
        } else {
            $properties['file_id'] = '';
        }

        if (!empty($properties['preview_id']) && $import_fs->has($files_path . '/' . $properties['preview_id'])) {
            $properties['preview_id'] = $this->medium_repo->addFileFromStream(
                $import_fs->readStream($files_path . '/' . $properties['preview_id']),
                $data['preview_name'] ?? ''
            );
        } else {
            $properties['preview_id'] = '';
        }

        self::setPCProperties($new_id, $properties);
        self::setPCVersion($new_id, $version);
    }
}
