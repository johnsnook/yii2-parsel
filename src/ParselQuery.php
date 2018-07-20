<?php

/**
 * This file is part of the Yii2 extension module, yii2-parsel
 * It's been heavily modified from the original by pimcore
 * @see https://github.com/pimcore/search-query-parser
 *
 * @author John Snook
 * @date 2018-07-28
 * @license https://github.com/johnsnook/yii2-parsel/LICENSE
 * @copyright 2018 John Snook Consulting
 */

namespace johnsnook\parsel;

use johnsnook\parsel\lib\Lexer;
use johnsnook\parsel\lib\Parser;
use johnsnook\parsel\lib\ParserException;
use yii\db\Query;
use yii\base\InvalidConfigException;

/**
 * The main class for this extension.  The main method is {{build}}.
 */
class ParselQuery {

    /**
     * @var string The message when a query can't be parsed.
     */
    private $lastError;

    /**
     * The main event.  Takes a database query, a user entered query and an
     * optional list of fields to search and returns the query with all the
     * where clauses and sub-queries ready for use in an \yii\data\ActiveDataProvider.
     *
     * @param yii\db\Query $query The database query we'll be adding to
     * @param string|array $userSearch The search string entered by the user, or the array of sub-query parts
     * @param array|null $fields The list of fields to include in our search.  If not specified, use text/varchar/char fields in select clause.  If * then use all searchable fields in table.
     * @return yii\db\Query The transformed database query.
     */
    public static function build($query, $userSearch, $fields = null) {
        $like = self::fuzzyOperator();
        /** If things go tits up, return the unmodified original. */
        $pQuery = clone $query;
        if (is_null($fields)) {
            $fields = self::fields($pQuery);
        }

        /**
         * This allows us to use this function recursively.  If its a string,
         * then this is our first pass.  If it's an array, we've recursed
         */
        if (gettype($userSearch) !== 'array') {
            if (empty($userSearch)) {
                return $pQuery;
            }
            try {
                $queryParts = self::parseQuery($userSearch);
            } catch (ParserException $pe) {
                /**
                 * Welp, something is borked.  Set the errormessage and bounce
                 */
                self::$lastError = $pe->message;
                return $query;
            }
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
                    $value = self::prepareTermValue($queryPart);
                    foreach ($fields as $field) {
                        if ($queryPart['fullMatch']) {
                            $where[] = [$field => $value]; //{$neg}
                        } else {
                            $where[] = [$like, $field, $value, false]; //{$neg}
                        }
                    }

                    /** Ties a not() around the condition(s) */
                    if ($queryPart['negated']) {
                        $where = ['not', $where];
                    }
                    if ($conjunction === 'AND') {
                        $pQuery->andWhere($where);
                    } elseif ($conjunction === 'OR') {
                        $pQuery->orWhere($where);
                    }
                    break;
                /**
                 * Get ready to recurse.  Create a new query with the same
                 * tables(s) but only returning the primary key.
                 */
                case "query":
                    $subQuery = new Query;
                    $pk = self::primaryKey($pQuery);
                    $subQuery->from($pQuery->from)->select($pk);
                    $subQuery = self::build($subQuery, $queryPart['items'], $fields);

                    /** Ties a not() around the condition(s) */
                    $neg = $queryPart['negated'] ? 'NOT ' : '';
                    $where = ["{$neg}IN", $pk, $subQuery];
                    if ($conjunction === 'AND') {
                        $pQuery->andWhere($where);
                    } elseif ($conjunction === 'OR') {
                        $pQuery->orWhere($where);
                    }
                    break;
                /** We hold on to the AND/OR for the next term/subquery */
                case "keyword":
                    $conjunction = strtoupper($queryPart['value']);
                    break;
            }
        }
        return $pQuery;
    }

    /**
     * Set which fuzzy database operator to use.  My favorite, postgresql uses
     * ILIKE, most others just use LIKE.
     * @todo figure out what all the other databases use.  I only checked a few.
     */
    private static function fuzzyOperator() {
        return (\Yii::$app->db->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE');
    }

    /**
     * Based on the metadata, get the term ready for a SQL statement
     *
     * @param array $term
     * @return string The modified value
     */
    public static function prepareTermValue($term) {
        $term = (object) $term;

        if ($term->fuzzy) {
            $value = str_replace(['*', '?'], ['%', '_'], $term->value);
            $split = str_split($value);
            /**
             * this part is just to make sure we don't end up with double
             * wildcards at the beginning or end of our term
             */
            if ($split[strlen($value) - 1] !== '%') {
                $value .= '%';
            }
            if ($split[0] !== '%') {
                $value = '%' . $value;
            }
        } elseif ($term->quoted === Parser::QUOTE_SINGLE) {
            /** single quote terms are literal, so escape any wildcard chars */
            $value = str_replace('%', '\%', str_replace('_', '\_', $term->value));
        } else {
            $value = '%' . $term->value . '%';
        }
        return $value;
    }

    /**
     * If fields are specified in the Query::select property, use those.  If not,
     * get introspective and use the fields in this table that are string types.
     *
     * @todo figure out ecumenical way to add other types, eg dates, numbers, json
     *
     * @param yii\db\Query $query
     * @return array The list of fields
     * @throws InvalidConfigException
     */
    public static function fields($query) {
        $return = [];
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

    /**
     * Figure out the primary key for use in sub-queries.
     *
     * @param yii\db\Query $query
     * @return string
     * @throws InvalidConfigException
     */
    public static function primaryKey($query) {
        $return = [];
        foreach ($query->tablesUsedInFrom as $alias => $tableName) {
            $fields = (is_null($query->select) ? '*' : $query->select);
            if ($meta = \Yii::$app->db->schema->getTableSchema($tableName)) {
                foreach ($meta->columns as $col) {
                    if ($col->isPrimaryKey) {
                        return "{$alias}.{$col->name}";
                    }
                }
            } else {
                /** if we didn't find a primary key in the first table, complain */
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

}
