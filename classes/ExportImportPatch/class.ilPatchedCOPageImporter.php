<?php

declare(strict_types=1);

class ilPatchedCOPageImporter extends ilCOPageImporter
{
    public function extractPluginProperties(ilPageObject $a_page): void
    {
        parent::extractPluginProperties($a_page);
    }

    public function replacePluginProperties(ilPageObject $a_page): bool
    {
        return parent::replacePluginProperties($a_page);
    }
}
