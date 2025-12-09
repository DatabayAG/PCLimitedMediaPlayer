<?php

declare(strict_types=1);

class ilPatchedCOPageExporter extends ilCOPageExporter
{
    /**
     * Make function public to be used in the text exporter directly
     */
    public function extractPluginProperties(ilPageObject $a_page): void
    {
        parent::extractPluginProperties($a_page);
    }

    /**
     * Give public access to property
     */
    public function getPluginDependencies(): array
    {
        return $this->plugin_dependencies;
    }
}
