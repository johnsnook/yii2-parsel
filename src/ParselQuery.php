<?php

/**
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace johnsnook\parsel;

use yii\db\Query;
use yii\base\InvalidConfigException;

class ParselQuery extends \yii\base\BaseObject {

    /**
     *
     * @param yii\db\Query $query The database query we'll be adding to
     * @param string $userSearch The search string entered by the user
     * @param array $fields The list of fields to include in our search.  If not specified, use text/varchar/char fields in select clause.  If * then use all searcable fields in table.
     * @return yii\db\Query The transformed database query.
     */
    public static function build($query, $userSearch, $fields = null) {
        if (is_null($fields)) {
            $fields = self::fields($query);
        }

        /** This allows us to use this function recursively */
        if (gettype($userSearch) !== 'array') {
            if (empty($userSearch)) {
                return $query;
            }
            $queryParts = self::parseQuery($userSearch);
        } else {
            $queryParts = $userSearch;
        }
        $conjunction = 'AND';
        foreach ($queryParts as $queryPart) {

            switch ($queryPart['type']) {
                /**
                 * This is the search term we're going to compare against the
                 * fields in our table, select or argument list of fields.
                 */
                case "term":
                    $where = ['or'];
                    $value = self::prepareSample($queryPart['value'], $queryPart['fuzzy']);
                    foreach ($fields as $field) {
                        $where[] = ["ilike", $field, $value, false]; //{$neg}
                    }

                    /** Ties a not() around the condition(s) */
                    if ($queryPart['negated']) {
                        $where = ['not', $where];
                    }
                    if ($conjunction === 'AND') {
                        $query->andWhere($where);
                    } elseif ($conjunction === 'OR') {
                        $query->orWhere($where);
                    }
                    break;
                /**
                 * Get ready to recurse.  Create a new query with the same
                 * tables(s) but only returning the primary key.
                 */
                case "query":
                    $subQuery = new Query;
                    $pk = self::primaryKey($query);
                    $subQuery->from($query->from)->select($pk);
                    $subQuery = self::build($subQuery, $queryPart['items'], $fields);
                    //self::dumpSql($subQuery);

                    /** Ties a not() around the condition(s) */
                    $neg = $queryPart['negated'] ? 'NOT ' : '';
                    $where = ["{$neg}IN", $pk, $subQuery];
                    if ($conjunction === 'AND') {
                        $query->andWhere($where);
                    } elseif ($conjunction === 'OR') {
                        $query->orWhere($where);
                    }
                    break;
                /** We hold on to the AND/OR for the next term/subquery */
                case "keyword":
                    $conjunction = strtoupper($queryPart['value']);
                    break;
            }
        }
        return $query;
    }

    public static function prepareSample($value, $isFuzzy) {
        $value = str_replace(['*', '?'], ['%', '_'], $value);
        if ($isFuzzy) {
            $split = str_split($value);
            if ($split[strlen($value) - 1] !== '%') {
                $value .= '%';
            }
            if ($split[0] !== '%') {
                $value = '%' . $value;
            }
            echo "$value strlen(\$value) = " . strlen($value) . PHP_EOL;
        } else {
            $value = '%' . $value . '%';
        }
        return $value;
    }

    public static function fields($query) {
        $return = [];
        //$modelClass = $query->tablesUsedInFrom;
        dump($query->tablesUsedInFrom);
        foreach ($query->tablesUsedInFrom as $alias => $tableName) {
            $fields = (is_null($query->select) ? '*' : $query->select);
            if ($meta = \Yii::$app->db->schema->getTableSchema($tableName)) {
                foreach ($meta->columns as $col) {
                    if ($fields === '*' || in_array($col->name, $fields)) {
                        /** only add strings */
                        if (in_array($col->type, ['string', 'text', 'char'])) {
                            $return [] = $alias . '.' . $col->name;
                        }
                    }
                }
            } else {
                throw new InvalidConfigException("Table: $tableName not found.");
            }
        }
        return $return;
    }

    public static function primaryKey($query) {
        $return = [];
        foreach ($query->tablesUsedInFrom as $alias => $tableName) {
            $fields = (is_null($query->select) ? '*' : $query->select);
            if ($meta = \Yii::$app->db->schema->getTableSchema($tableName)) {
                foreach ($meta->columns as $col) {
                    if ($col->isPrimaryKey) {
                        return $col->name;
                    }
                }
            } else {
                throw new InvalidConfigException("Table: $tableName not found.");
            }
        }
        throw new InvalidConfigException("Table: $tableName doesn't have a primary key defined, which is required for subquerys.");
    }

    /**
     * Ties together the lexer & parser and returns an array representing the
     * query
     * @param string $queryString
     * @return array
     */
    private static function parseQuery($queryString) {
        $lexer = new Lexer();
        $tokens = $lexer->lex($queryString);
        //dump($tokens);
        $parser = new Parser();
//        return $parser->parse($lexer->lex($queryString))->toArray();
        return $parser->parse($tokens);
    }

    private static function dumpSql($queryObj) {
        echo SqlFormatter::format($queryObj->createCommand()->getRawSql()) . PHP_EOL;
    }

}
