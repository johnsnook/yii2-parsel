<?php

use yii\db\Migration;

class m180726_045949_create_table_script extends Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%script}}', [
            'id' => $this->primaryKey(),
            'character' => $this->string(),
            'dialog' => $this->text(),
            'episode_id' => $this->integer(),
        ], $tableOptions);

        $this->createIndex('script_character_id', '{{%script}}', 'character');
        $this->createIndex('script_dialog_idx', '{{%script}}', 'dialog');
        $this->addForeignKey('episode_fk', '{{%script}}', 'episode_id', '{{%episode}}', 'id', 'NO ACTION', 'NO ACTION');
    }

    public function down()
    {
        $this->dropTable('{{%script}}');
    }
}
