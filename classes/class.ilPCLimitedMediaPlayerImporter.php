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

        // get the properties that are export as XML
        // most of them are already properties on the page
        $export = [];
        $xml = new SimpleXMLElement($a_xml);
        foreach ($xml->children() as $name => $value) {
            $value = (string) $value;
            $export[$name] = empty($value) ? null : $value;
        }

        if (!empty($properties['file_id']) && $import_fs->has($files_path . '/' . $properties['file_id'])) {
            $properties['file_id'] = $this->medium_repo->addFileFromStream(
                $import_fs->readStream($files_path . '/' . $properties['file_id']),
                $export['FileName'] ?? ''
            );
        } else {
            $properties['file_id'] = '';
        }

        if (!empty($properties['preview_id']) && $import_fs->has($files_path . '/' . $properties['preview_id'])) {
            $properties['preview_id'] = $this->medium_repo->addFileFromStream(
                $import_fs->readStream($files_path . '/' . $properties['preview_id']),
                $export['PreviewName'] ?? ''
            );
        } else {
            $properties['preview_id'] = '';
        }

        self::setPCProperties($new_id, $properties);
        self::setPCVersion($new_id, $version);
    }
}
