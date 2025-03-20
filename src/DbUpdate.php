<?php

declare(strict_types=1);

namespace ILIAS\Plugin\LimitedMediaPlayer;

use ilDatabaseUpdateSteps;
use ilDBInterface;
use ilDBStepExecutionDB;
use ilDBStepReader;
use Exception;

class DbUpdate implements ilDatabaseUpdateSteps
{
    private ilDBInterface $db;

    public function prepare(ilDBInterface $db): void
    {
        $this->db = $db;
    }

    public function execute(): void
    {
        $execution_log = new ilDBStepExecutionDB($this->db, fn () => new \DateTime());
        $step_reader = new ilDBStepReader();

        $last_started_step = $execution_log->getLastStartedStep(self::class);
        $last_finished_step = $execution_log->getLastFinishedStep(self::class);

        foreach ($step_reader->readStepNumbers(self::class, 'step_') as $step) {
            if ($step <= $last_finished_step) {
                continue;
            }
            $execution_log->started(self::class, $step);
            $method = 'step_' . $step;
            try {
                $this->$method();
            }
            catch (Exception $e) {
                $this->revertStep($step);
                throw $e;
            }
            $execution_log->finished(self::class, $step);
        }
    }

    public function uninstall(): void
    {
        $this->db->dropTable('limply_uses', false);
        $this->db->dropTable('limply_limit', false);
        $this->db->manipulateF("DELETE FROM il_db_steps WHERE class = %s", ['text'], [self::class]);
    }

    private function revertStep(int $step): void
    {
        $this->db->manipulateF("DELETE FROM il_db_steps WHERE class = %s AND step = %s",
            ['text', 'integer'], [self::class, $step]);
    }

    /**
     * Table for usage recording
     */
    public function step_1(): void
    {
        // step should be repeatable until everything is created
        $this->db->dropTable('limply_uses', false);

        $this->db->createTable(
            'limply_uses',
            [
                'parent_id'     => ['type' => 'integer',    'length' => 4,  'notnull' => true],
                'page_id'       => ['type' => 'integer',    'length' => 4,  'notnull' => true],
                'file_id'       => ['type' => 'text',       'length' => 64, 'notnull' => true],
                'user_id'       => ['type' => 'integer',    'length' => 4,  'notnull' => true],
                'plays'         => ['type' => 'integer',    'length' => 4,  'notnull' => true],
                'seconds'       => ['type' => 'float',                      'notnull' => false, 'default' => null],
                'pass'          => ['type' => 'integer',    'length' => 4,  'notnull' => false, 'default' => null],
                'active_id'     => ['type' => 'integer',    'length' => 4,  'notnull' => false, 'default' => null],
            ],
            false
        );
        $this->db->addPrimaryKey('limply_uses', ['parent_id', 'page_id', 'file_id', 'user_id']);
        $this->db->createSequence('limply_uses');
    }

    /**
     * Migrate usage recording from version 1
     */
    public function step_2(): void
    {
        if ($this->db->tableExists('copg_pgcp_limply_uses')) {

            // step should be repeatable until old table is dropped
            $this->db->manipulate("TRUNCATE TABLE limply_uses");

            $result = $this->db->query("SELECT * FROM copg_pgcp_limply_uses");
            while ($row = $this->db->fetchAssoc($result)) {
                $this->db->insert('limply_uses', [
                    'parent_id' => ['integer', $row['parent_id']],
                    'page_id'   => ['integer', $row['page_id']],
                    'file_id'   => ['text',    $row['mob_id']],
                    'user_id'   => ['integer', $row['user_id']],
                    'plays'     => ['integer', $row['plays']],
                    'seconds'   => ['integer', $row['seconds'] == -1 ? null : $row['seconds']],
                    'pass'      => ['integer', $row['pass'] == -1 ? null : $row['pass']],
                    'active_id' => ['integer', $row['active_id'] == -1 ? null : $row['active_id']],

                ]);
            }

            $this->db->dropTable('copg_pgcp_limply_uses');
        }
    }

    /**
     * Table for custom limit settings
     */
    public function step_3(): void
    {
        // step should be repeatable until everything is created
        $this->db->dropTable('limply_limit', false);

        $this->db->createTable(
            'limply_limit',
            [
                'id'            => ['type' => 'integer',    'length' => 4,  'notnull' => true],
                'parent_id'     => ['type' => 'integer',    'length' => 4,  'notnull' => true],
                'page_id'       => ['type' => 'integer',    'length' => 4,  'notnull' => false, 'default' => null],
                'file_id'       => ['type' => 'text',       'length' => 64, 'notnull' => false, 'default' => null],
                'user_id'       => ['type' => 'integer',    'length' => 4,  'notnull' => false, 'default' => null],
                'plays'         => ['type' => 'integer',    'length' => 4,  'notnull' => false, 'default' => null],
            ],
            false
        );
        $this->db->addPrimaryKey('limply_limit', ['id']);
        $this->db->addIndex('limply_limit', ['parent_id'], 'i1');
        $this->db->addIndex('limply_limit', ['page_id'], 'i2');
        $this->db->addIndex('limply_limit', ['file_id'], 'i3');
        $this->db->addIndex('limply_limit', ['user_id'], 'i4');
        $this->db->createSequence('limply_limit');
    }

    /**
     * Migrate usage recording from version 1
     */
    public function step_4(): void
    {
        if ($this->db->tableExists('copg_pgcp_limply_limit')) {

            // step should be repeatable until old table is dropped
            $this->db->manipulate("TRUNCATE TABLE limply_limit");

            $result = $this->db->query("SELECT * FROM copg_pgcp_limply_limit");
            while ($row = $this->db->fetchAssoc($result)) {
                $this->db->insert('limply_limit', [
                    'id'        => ['integer', $this->db->nextId('limply_limit')],
                    'parent_id' => ['integer', $row['parent_id']],
                    'page_id'   => ['integer', $row['page_id'] == 0 ? null : $row['page_id']],
                    'file_id'   => ['text',    $row['mob_id'] == 0 ? null : (string) $row['mob_id']],
                    'user_id'   => ['integer', $row['page_id'] == 0 ? null : $row['page_id']],
                    'plays'     => ['integer', $row['plays']],
                ]);
            }

            $this->db->dropTable('copg_pgcp_limply_limit');
        }
    }

    /**
     * todo: Migrate existing media to file resources
     */
    public function _step_5(): void
    {
        //  search for page contents
        //  extract the parameters
        //  find the media object
        //  move the media files to irss
        //  adapt the page content properties
        //  replace file_ids in limply_uses and limply_limit
        //  remove the media object from the page
    }
}
