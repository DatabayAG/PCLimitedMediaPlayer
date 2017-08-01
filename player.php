<?php
/**
 * Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

define('LIMPLY_BACKSTEPS', '../../../../../../../');
chdir(LIMPLY_BACKSTEPS);

require_once("Services/Init/classes/class.ilInitialisation.php");
ilInitialisation::initILIAS();

require_once __DIR__ . "/classes/class.ilLimitedMediaPlayerGUI.php";
$player = new ilLimitedMediaPlayerGUI();
$player->executeCommand();
?>