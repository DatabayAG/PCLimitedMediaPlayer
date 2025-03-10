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

    if (!$ilDB->tableExists('copg_pgcp_limply_limit'))
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
        $ilDB->createTable("copg_pgcp_limply_limit", $fields);
        $ilDB->addPrimaryKey("copg_pgcp_limply_limit", array("parent_id", "page_id", "mob_id", "user_id"));
    }
?>
<#3>
<?php
    if(!$ilDB->tableColumnExists('copg_pgcp_limply_uses', 'active_id'))
    {
    	$ilDB->addTableColumn('copg_pgcp_limply_uses', 'active_id', array(
    			'type' => 'integer',
    			'length' => 4,
    			'notnull' => true,
    			'default' => -1
    		)
        );
    }
?>
<#4>
<?php
    // version 2: use null to indicate 'not played'
    $ilDB->modifyTableColumn('copg_pgcp_limply_uses', 'seconds', array(
        'type' => 'float',
        'notnull' => false,
        'default' => null
    ));
    $ilDB->manipulate('UPDATE copg_pgcp_limply_uses SET seconds = NULL WHERE seconds = -1');
?>
<#5>
<?php
    // version 2: use null for missing pass
    $ilDB->modifyTableColumn('copg_pgcp_limply_uses', 'pass', array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => false,
        'default' => null
    ));
    $ilDB->manipulate('UPDATE copg_pgcp_limply_uses SET pass = NULL WHERE pass = -1');
?>
<#6>
<?php
    // version 2: use null for missing active_id
    $ilDB->modifyTableColumn('copg_pgcp_limply_uses', 'active_id', array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => false,
        'default' => null
    ));
    $ilDB->manipulate('UPDATE copg_pgcp_limply_uses SET active_id = NULL WHERE active_id = -1');
?>
<#7>
<?php
    // version 2: use null for missing page_id
    $ilDB->modifyTableColumn('copg_pgcp_limply_limit', 'page_id', array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => false,
        'default' => null
    ));
    $ilDB->manipulate('UPDATE copg_pgcp_limply_limit SET page_id = NULL WHERE page_id = 0');
?>
<#8>
<?php
    // version 2: use null for missing mob_id
    $ilDB->modifyTableColumn('copg_pgcp_limply_limit', 'mob_id', array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => false,
        'default' => null
    ));
    $ilDB->manipulate('UPDATE copg_pgcp_limply_limit SET mob_id = NULL WHERE mob_id = 0');
?>
<#9>
<?php
    // version 2: use null for missing user_id
    $ilDB->modifyTableColumn('copg_pgcp_limply_limit', 'user_id', array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => false,
        'default' => null
    ));
    $ilDB->manipulate('UPDATE copg_pgcp_limply_limit SET user_id = NULL WHERE user_id = 0');
?>
<#10>
<?php
    // version 2: use null ti indicate unlimited plays
    $ilDB->modifyTableColumn('copg_pgcp_limply_limit', 'limit_plays', array(
        'type' => 'integer',
        'length' => 4,
        'notnull' => false,
        'default' => null
    ));
    $ilDB->manipulate('UPDATE copg_pgcp_limply_limit SET limit_plays = NULL WHERE limit_plays = 0');
?>
