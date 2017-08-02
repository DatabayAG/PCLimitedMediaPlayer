
Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
GPLv3, see LICENSE

Author: Fred Neumann <fred.neumann@gmx.de>


This plugin for ILIAS open source provides a new element in the page editor of test questions.
An audio or video file can be uploaded there and a limit of replays can be set. Students doing
the test will have player with restricted controls and counting of replays. If the maximum number
of replays is reached, then the medium is not longer accessible for the student.
The counting of replays can be related to the running test pass, the browser session of the student
or be a general limit.


INSTALLATION
------------

1. Put the content of the plugin directory in a subdirectory under your ILIAS main directory:
Customizing/global/plugins/Services/COPage/PageComponent/PCLimitedMediaPlayer

2. Open ILIAS > Administration > Plugins

3. Update/Activate the Plugin.


USAGE
-----

Edit a test question. Chose "Insert Limited Media Player". Select an audio or video file and
some additional properties, e.g. the counting context or if pausing is allowed. Leave the width and
height empty for audio and choose an appropriate size for video. Save the element.

Due to restrictions of the plugin interface in ILIAS up to 5.2, the file has to be added as a deactivated
media object on the page. Please don't edit this object directly but use the element of the limited player
to edit it. If you want to delete the player element, please delete also the media file.
