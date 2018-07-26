<?php

namespace johnsnook\parsel\models;

use frontend\models\Script;
use johnsnook\parsel\ParselQuery;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\Expression;
use yii\helpers\StringHelper;

/**
 * ScriptSearch represents the model behind the search form of `common\models\Script`.
 */
class ScriptSearch extends Script {

    /**
     * @var string Virtual field to pass user query for yii2-parsel
     */
    public $userQuery;

    /**
     * @var ParselQuery
     */
    public $parsel;

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['id', 'episode', 'season'], 'integer'],
            /** 'userQuery' must be added to 'safe' rule */
            [['character', 'dialog', 'seid', 'userQuery', 'air_date'], 'safe'],
        ];
    }

    public static function basename() {
        return StringHelper::basename(__class__);
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios() {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params) {

        /**
         * to give a full example, we'll join with a parent table and use an
         * expression to search a non-text field
         */
        $season = new Expression("to_char(season, '99')");
        $episode = new Expression("to_char(episode, '99')");
        $air_date = new Expression("to_char(air_date, 'Mon dd, yyyy')");

        /**
         * @var array The fields to search with ParselQuery
         */
        $fields = [
            'character',
            'dialog',
            'seid',
            'season' => $season,
            'episode' => $episode,
            'air_date' => $air_date
        ];

        $this->load($params);

//        if (!empty($this->userQuery)) {
//            $query = ParselQuery::build($query, $this->userQuery, $fields); //
//            $this->queryError = ParselQuery::$lastError;
//            if (!empty($this->queryError)) {
//                return $dataProvider;
//            }
//        }

        $this->parsel = new ParselQuery([
            'userQuery' => $this->userQuery,
            'searchFields' => $fields,
            'dbQuery' => Script::find()
                    ->select(array_merge(['id' => 'script.id',], $fields))
                    ->leftJoin('episode e', 'episode_id = e.id')
                    ->orderBy(['script.id' => SORT_ASC])
        ]);
        // add conditions that should always apply here
        $dataProvider = new ActiveDataProvider([
            'query' => $this->parsel->dbQuery,
            'pagination' => [
                'pageSize' => 20,
            ],
//            'sort' => [
//                'defaultOrder' => [
//                    'id' => SORT_ASC,
//                ]
//            ],
        ]);

        return $dataProvider;
    }

}
