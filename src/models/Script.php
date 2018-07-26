<?php

namespace johnsnook\parsel\models;

use Yii;

/**
 * This is the model class for table "script".
 *
 * @property int $id
 * @property string $character
 * @property string $dialog
 * @property int $episode
 * @property string $seid
 * @property int $season
 */
class Script extends \yii\db\ActiveRecord {

    public $seid;
    public $air_date;

    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'script';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['id'], 'required'],
            [['id', 'episode_id'], 'default', 'value' => null],
            [['id', 'episode_id'], 'integer'],
            [['character', 'dialog'], 'string'],
            [['id'], 'unique'],
            [['episode_id'], 'exist', 'skipOnError' => true, 'targetClass' => Episode::className(), 'targetAttribute' => ['episode_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'id' => 'ID',
            'character' => 'Character',
            'dialog' => 'Dialog',
            'episode_id' => 'Episode ID',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getEpisode() {
        return $this->hasOne(Episode::className(), ['id' => 'episode_id']);
    }

}
