<?php

namespace johnsnook\parsel\models;

use Yii;

/**
 * This is the model class for table "episode".
 *
 * @property int $id
 * @property int $season
 * @property int $episode
 * @property string $title
 * @property string $air_date
 * @property string $writers
 * @property string $director
 * @property string $seid
 *
 * @property Script[] $scripts
 */
class Episode extends \yii\db\ActiveRecord {

    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'episode';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['id'], 'required'],
            [['id', 'season', 'episode'], 'default', 'value' => null],
            [['id', 'season', 'episode'], 'integer'],
            [['title', 'writers', 'director'], 'string'],
            [['air_date'], 'safe'],
            [['seid'], 'string', 'max' => 6],
            [['id'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'id' => 'ID',
            'season' => 'Season',
            'episode' => 'Episode',
            'title' => 'Title',
            'air_date' => 'Air Date',
            'writers' => 'Writers',
            'director' => 'Director',
            'seid' => 'Seid',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getScripts() {
        return $this->hasMany(Script::className(), ['episode_id' => 'id']);
    }

}
