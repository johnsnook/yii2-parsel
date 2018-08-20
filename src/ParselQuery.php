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
use johnsnook\parsel\lib\SqlFormatter;
use yii\base\InvalidConfigException;

/**
 * The main class for this extension.  The main method is [[processQuery]].
 * @property-read string $lastError The message when a query can't be parsed.
 * @property-read array $tokens The tokens created by [[Lexer::lex]].
 * @property-read array $queryParts The query parts created by [[Parser::parse]].
 * @property-read string $sql The sql query.
 * @property [[yii\db\Query]] $dbQuery The database query we'll be adding to
 * @property string|array $userQuery The search string entered by the user, or the array of sub-query parts
 * @property array|null $searchFields The list of fields to include in our search.  If not specified, use text/varchar/char fields in select clause.  If * then use all searchable fields in table.
 */
class ParselQuery extends \yii\base\BaseObject {

    /**
     * @var string The message when a query can't be parsed.
     */
    private $lastError;

    /**
     * @var yii\db\Query  The database query we'll be adding to
     */
    private $dbQuery;

    /**
     * @var string|array $userQuery The search string entered by the user, or the array of sub-query parts
     */
    private $userQuery;

    /**
     * @var array|null The list of fields to include in our search.  If not specified, use text/varchar/char fields in select clause.  If * then use all searchable fields in table.
     */
    private $searchFields;

    /**
     * The main event.  Takes a database query, a user entered query and an
     * optional list of fields to search and returns the query with all the
     * where clauses and sub-queries ready for use in an \yii\data\ActiveDataProvider.
     *
     * @param yii\db\Query $dbQuery The database query we'll be adding to
     * @param array $queryParts The array of sub-query parts
     * @param array|null $fields The list of fields to include in our search.  If not specified, use text/varchar/char fields in select clause.  If * then use all searchable fields in table.
     * @return yii\db\Query The transformed database query.
     */
    protected function processQuery($dbQuery, $queryParts, $fields = null) {

        /** replace numeric keys with the value */
        foreach ($fields as $key => $item) {
            if (is_numeric($key)) {
                $fields[$item] = $item;
                unset($fields[$key]);
            }
        }

        $this->lastError = false;
        $like = self::fuzzyOperator();
        /** If things go tits up, return the unmodified original. */
        $pQuery = clone $dbQuery;
        if (is_null($fields)) {
            if (is_null($pQuery->select)) {
                $fields = self::fields($pQuery);
            } else {
                $fields = $pQuery->select;
            }
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

                    /**
                     * allow the user to specify a single field.
                     * 1) Copy the fields array to fieldlist
                     * 5) set the fieldlist to the single field specified in term
                     */
                    $fieldlist = $fields;
                    if (isset($queryPart['field'])) {
                        if (in_array($queryPart['field'], array_keys($fields), true)) {
                            $fieldExpression = $fields[$queryPart['field']];
                            $fieldlist = [$fieldExpression];
                        }
                    }

                    $value = self::prepareTermValue($queryPart);
                    foreach ($fieldlist as $field) {
                        if ($queryPart['fullMatch'] && !$queryPart['fuzzy']) {
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
                    $subQuery = clone $dbQuery;
                    $pk = self::primaryKey($pQuery);
                    $subQuery->select($pk);
                    $subQuery = $this->processQuery($subQuery, $queryPart['items'], $fields);
                    /** Ties a not() around the condition(s) */
                    $not = $queryPart['negated'] ? 'NOT ' : '';
                    $where = ["{$not}IN", $pk, $subQuery];
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
     * Begins the work of lexing, parsing and processing then returns the Query
     *
     * @return yii\db\Query
     */
    public function getDbQuery() {
        $queryParts = $this->queryParts;
        return $this->processQuery($this->dbQuery, $queryParts, $this->searchFields);
    }

    /**
     *
     * @param yii\db\Query $val
     */
    public function setDbQuery($val) {
        $this->dbQuery = $val;
    }

    /**
     * Get the last error, if any
     * @return string
     */
    public function getLastError() {
        return $this->lastError;
    }

    /**
     * Requests the tokens (lexing) then calls the parser and returns the array of
     * query parts to process into a where clause
     *
     * @return array
     */
    public function getQueryParts() {
        $parser = new Parser();
        $tokens = $this->tokens;
        $queryParts = [];
        try {
            $queryParts = $parser->parse($tokens);
        } catch (ParserException $pe) {
            /**
             * Welp, something is borked.  Set the errormessage and bounce
             */
            $this->lastError = $pe->getMessage();
        }
        return $queryParts;
    }

    /**
     * The list of fields to search
     *
     * @return array
     */
    public function getSearchFields() {
        return $this->searchFields;
    }

    /**
     * Assign the list of fields to search
     *
     * @param array $val
     */
    public function setSearchFields($val) {
        $this->searchFields = $val;
    }

    /**
     *
     * @return string The sql string generated by ParselQuery. For debugging purposes
     */
    public function getSql() {
        return SqlFormatter::format($this->getDbQuery()->createCommand()->getRawSql());
    }

    /**
     * Discerns the lexemes of the users query string
     *
     * @return array
     */
    public function getTokens() {
        $lexer = new Lexer();
        return $lexer->lex($this->userQuery);
    }

    /**
     * The users query string
     *
     * @return string
     */
    public function getUserQuery() {
        return $this->userQuery;
    }

    /**
     * The users query string
     *
     * @param string $val
     */
    public function setUserQuery($val) {
        $this->userQuery = $val;
    }

    /**
     * Set which fuzzy database operator to use.  My favorite, postgresql uses
     * ILIKE, most others just use LIKE.
     * @todo figure out what all the other databases use.  I only checked a few.
     */
    protected static function fuzzyOperator() {
        return (\Yii::$app->db->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE');
    }

    /**
     * Based on the metadata, get the term ready for a SQL statement.  Also
     * replace user tokens with db tokens
     *
     * @param array $term
     * @return string The modified value
     */
    private static function prepareTermValue($term) {
        $term = (object) $term;

        if ($term->fuzzy) {
            $value = str_replace(['*', '?'], ['%', '_'], $term->value);
            $split = str_split($value);
            /**
             * this part is just to make sure we don't end up with double
             * wildcards at the beginning or end of our term
             */
            if (($split[strlen($value) - 1] !== '%' ) && (!$term->fullMatch)) {
                $value .= '%';
            }
            if (($split[0] !== '%' ) && (!$term->fullMatch)) {
                $value = '%' . $value;
            }
        } elseif ($term->quoted === Parser::QUOTE_SINGLE) {
            /** single quote terms are literal, so escape any wildcard chars */
            $value = str_replace('%', '\%', str_replace('_', '\_', $term->value));
        } elseif ($term->fullMatch) {
            $value = $term->value;
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
     * @param yii\db\Query $dbQuery
     * @return array The list of fields
     * @throws InvalidConfigException
     */
    private static function fields($dbQuery) {
        $return = [];
        foreach ($dbQuery->tablesUsedInFrom as $alias => $tableName) {
            $fields = (is_null($dbQuery->select) ? '*' : $dbQuery->select);
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
     * @param yii\db\Query $dbQuery
     * @return string
     * @throws InvalidConfigException
     */
    private static function primaryKey($dbQuery) {

        foreach ($dbQuery->tablesUsedInFrom as $alias => $tableName) {
            if ($meta = \Yii::$app->db->schema->getTableSchema($tableName)) {
                if ($pk = $meta->primaryKey) {
                    return "{$alias}.{$pk}";
                }
            } else {
                /** if we didn't find a primary key in the first table, complain */
                throw new InvalidConfigException("Table: $tableName not found.");
            }
        }
        $tables = implode(',', $dbQuery->tablesUsedInFrom);
        throw new InvalidConfigException("Table(s): $tables doesn't have a primary key defined, which is required for subquerys.");
    }

}
