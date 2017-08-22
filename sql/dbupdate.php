<#1>
<?php
    /**
     * Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
     * GPLv3, see docs/LICENSE
     */

    /**
     * Limited Media Player plugin: database update script
     *
     * @author Fred Neumann <fred.neumann@fau.de>
     * @version $Id$
     */

    if (!$ilDB->tableExists('copg_pgcp_limply_uses'))
    {
        $fields = array(
            'parent_id' => array(
                'type' => 'integer',
                'length' => 4,
                'notnull' => true
            ),
            'page_id' => array(
                'type' => 'integer',
                'length' => 4,
                'notnull' => true
            ),
            'mob_id' => array(
                'type' => 'integer',
                'length' => 4,
                'notnull' => true
            ),
            'user_id' => array(
                'type' => 'integer',
                'length' => 4,
                'notnull' => true
            ),
            'plays' => array(
                'type' => 'integer',
                'length' => 4,
                'notnull' => true,
                'default' => 0
            ),
            'seconds' => array(
                'type' => 'float',
                'notnull' => true,
                'default' => -1
            ),
            'pass' => array(
                'type' => 'integer',
                'length' => 4,
                'notnull' => true,
                'default' => -1
            )
        );
        $ilDB->createTable("copg_pgcp_limply_uses", $fields);
        $ilDB->addPrimaryKey("copg_pgcp_limply_uses", array("parent_id", "page_id", "mob_id", "user_id"));
    }
?>
<#2>
<?php

    if (!$ilDB->tableExists('copg_pgcp_limply_limits'))
    {
        $fields = array(
            'parent_id' => array(
                'type' => 'integer',
                'length' => 4,
                'notnull' => true
            ),
            'page_id' => array(
                'type' => 'integer',
                'length' => 4,
                'notnull' => true,
                'default' => 0
            ),
            'mob_id' => array(
                'type' => 'integer',
                'length' => 4,
                'notnull' => true,
                'default' => 0
            ),
            'user_id' => array(
                'type' => 'integer',
                'length' => 4,
                'notnull' => true,
                'default' => 0
            ),
            'limit_plays' => array(
                'type' => 'integer',
                'length' => 4,
                'notnull' => true,
                'default' => 0
            )
        );
        $ilDB->createTable("copg_pgcp_limply_limits", $fields);
        $ilDB->addPrimaryKey("copg_pgcp_limply_limits", array("parent_id", "page_id", "mob_id", "user_id"));
    }
?>