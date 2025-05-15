
Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
GPLv3, see LICENSE

**Further maintenance can be offered by [Databay AG](https://www.databay.de).**

This plugin for ILIAS open source provides a new element in the page editor of test questions.
An audio or video file can be uploaded there and a limit of replays can be set. Students doing
the test will have player with restricted controls and counting of replays. If the maximum number
of replays is reached, then the medium is not longer accessible for the student.
The counting of replays can be related to the running test pass, the browser session of the student
or be a general limit.


# INSTALLATION


1. Put the content of the plugin directory in a subdirectory under your ILIAS main directory:
Customizing/global/plugins/Services/COPage/PageComponent/PCLimitedMediaPlayer
2. Run `composer du` in the main directory of your ILIAS installation
3. Go to Administration > Extending ILIAS > Plugins
4. Install and activate the plugin

## Patch for export and import

This plugin includes a path of the ILIAS export and import that fixes a missing handling of 
additional data in page component plugins when tests or question pools are exported and imported.

The problem is described here:
https://mantis.ilias.de/view.php?id=44719

The patch replaces the ILIAS class ilImportExportFactory to use customized exporter and importer classes for
the ILIAS test and question pool. When you call `composer install`, you will get a warning like this:

````
Warning: Ambiguous class resolution, "ilImportExportFactory" was found in both "/var/www/projects/ilias8/Customizing/global/plugins/Services/COPage/PageComponent/PCLimitedMediaPlayer/classes/ExportImportPatch/class.ilImportExportFactory.php" and "/var/www/projects/ilias8/Services/Export/classes/class.ilImportExportFactory.php", the first will be used.
````
This warning shows you that the patch is active. 

# USAGE

Edit a test question. Chose "Insert Limited Media Player". Select an audio or video file and
some additional properties, e.g. the counting context or if pausing is allowed. Leave the width and
height empty for audio and choose an appropriate size for video. Save the element.

