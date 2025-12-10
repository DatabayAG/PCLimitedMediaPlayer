<?php

declare(strict_types=1);

class ilPatchedCOPageImporter extends ilCOPageImporter
{
    /**
     * Make function public to be used in the text importer directly
     */
    public function extractPluginProperties(ilPageObject $a_page): void
    {
        parent::extractPluginProperties($a_page);
    }
}
