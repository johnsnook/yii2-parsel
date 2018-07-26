<?php

use yii\db\Migration;

class m180726_045934_create_table_episode extends Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%episode}}', [
            'id' => $this->primaryKey(),
            'season' => $this->integer(),
            'episode' => $this->integer(),
            'title' => $this->string(),
            'air_date' => $this->date(),
            'writers' => $this->string(),
            'director' => $this->string(),
            'seid' => $this->char(),
        ], $tableOptions);

        $this->createIndex('episode_title_idx', '{{%episode}}', 'title');
    }

    public function down()
    {
        $this->dropTable('{{%episode}}');
    }
}
