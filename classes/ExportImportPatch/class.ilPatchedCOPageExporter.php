<?php

declare(strict_types=1);

class ilPatchedCOPageExporter extends ilCOPageExporter
{
    public function extractPluginProperties(ilPageObject $a_page): void
    {
        parent::extractPluginProperties($a_page);
    }

    public function getPluginDependencies(): array
    {
        return $this->plugin_dependencies;
    }
}
