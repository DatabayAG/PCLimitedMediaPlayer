<?php
/**
 * Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

const LIMPLY_BACKSTEPS = '../../../../../../../';
chdir(LIMPLY_BACKSTEPS);

require_once("Services/Init/classes/class.ilInitialisation.php");
ilInitialisation::initILIAS();

$player = new \ILIAS\Plugin\LimitedMediaPlayer\Player();
$player->handleRequest();
?>