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

namespace johnsnook\parsel\lib;

class Parser {

    const QUOTE_NONE = null;
    const QUOTE_SINGLE = "'";
    const QUOTE_DOUBLE = '"';

    /**
     * @param Token[] $tokens The token lexemes from Lexer.php
     *
     * @return array An array representing the terms, conjunctions and subqueries and their properties
     */
    public function parse($tokens) {
        self::sanityCheck($tokens);
        /** @var Query[] $queryStack */
        $currentQuery = [];

        /** @var Token $previousToken */
        $previousToken = null;

        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            // brace open/close - sub-queries
            if ($token->isTypeOf(Tokens::BRACE_OPEN)) {
                $negated = self::isNegated($i, $tokens);
                $currentQuery = self::conjunctionJunction($currentQuery);
                $braceClosePos = self::findMatchPos($tokens, $i);
                $subwayTokens = array_slice($tokens, $i + 1, $braceClosePos - $i - 1);

                $currentQuery[] = [
                    'type' => 'query',
                    'negated' => $negated,
                    'items' => $this->parse($subwayTokens)
                ];
                $i = $braceClosePos;

                continue;
            }

            // terms (the actual values we're looking for)
            if (in_array($token->getToken(), Tokens::getTermTokens())) {
                $value = self::normalizeTerm($token);
                $term = [
                    'type' => 'term',
                    'value' => $value,
                    'fuzzy' => self::isFuzzy($token, $value),
                    'negated' => self::isNegated($i, $tokens),
                    'fullMatch' => self::isFullMatch($i, $tokens),
                    'quoted' => self::quoteType($token)
                ];

                $currentQuery = self::conjunctionJunction($currentQuery);
                $currentQuery[] = $term;
            } // terms

            /** Keywords */
            if ($token->isTypeOf(Tokens::KEYWORD)) {
                if ($previousToken && $previousToken->isTypeOf(Tokens::KEYWORD)) {
                    throw new ParserException(sprintf(
                            'Keyword can\'t be succeeded by another keyword (%s %s)', $previousToken->getContent(), $token->getContent()
                    ));
                }
                $currentQuery[] = ['type' => 'keyword', 'value' => strtoupper($token->getContent())];
            }
            $previousToken = $token;
        }
        return $currentQuery;
    }

    private static function findMatchPos($tokens, $i) {
        $j = 0;
        for ($i; $i <= count($tokens) + 1; $i++) {
            $j += ($tokens[$i]->token === Tokens::BRACE_OPEN ? 1 : 0 );
            if ($tokens[$i]->token === Tokens::BRACE_CLOSE) {
                if (--$j < 1) {
                    return $i;
                }
            }
        }
    }

    private static function conjunctionJunction($currentQuery) {
        // add an AND/OR before inserting the term if the last part was no keyword
        $lastPart = self::getLastPart($currentQuery);

        if (isset($lastPart['type']) && ($lastPart['type'] !== 'keyword')) {
            $currentQuery[] = ['type' => 'keyword', 'value' => 'AND'];
        }
        return $currentQuery;
    }

    private static function sanityCheck($tokens) {
        /** check to see if all braces are balanced */
        $left = $right = 0;
        foreach ($tokens as $token) {
            $left += $token->name === 'BRACE_OPEN' ? 1 : 0;
            $right += $token->name === 'BRACE_CLOSE' ? 1 : 0;
        }
        if ($left !== $right) {
            throw new ParserException("The query has $left left braces and $right right braces.");
        }
    }

    /**
     * Check if the token is fuzzy (is not single quoted and contains *)
     *
     * @param Token $token
     * @param string $value
     *
     * @return bool
     */
    private static function isFuzzy($token, $value) { #: bool
        if ($token->isTypeOf([Tokens::TERM_QUOTED_SINGLE])) {
            return false;
        }
        return false !== (strpos($value, '*') || strpos($value, '?'));
    }

    /**
     * Check if the token is fuzzy (is not single quoted and contains *)
     *
     * @param Token $token
     * @param string $value
     *
     * @return bool
     */
    private static function quoteType($token) { #: bool
        if ($token->isTypeOf(Tokens::TERM_QUOTED)) {
            return self::QUOTE_DOUBLE;
        }
        if ($token->isTypeOf(Tokens::TERM_QUOTED_SINGLE)) {
            return self::QUOTE_SINGLE;
        }
        return self::QUOTE_NONE;
    }

    /**
     * Check if expression is a full match by looking back at previous tokens
     *
     * @param int $index
     * @param Token[] $tokens
     *
     * @return bool
     */
    private static function isFullMatch($index, $tokens) { //: bool
        $fullMatch = false;

        $startIndex = $index - 1;
        if ($startIndex < 0) {
            return $fullMatch;
        }

        for ($i = $startIndex; $i >= 0; $i--) {
            if ($tokens[$i]->isTypeOf(Tokens::FULL_MATCH)) {
                $fullMatch = !$fullMatch;
            } else {
                break;
            }
        }

        return $fullMatch;
    }

    /**
     * Check if expression was negated by looking back at previous tokens
     *
     * @param int $index
     * @param Token[] $tokens
     *
     * @return bool
     */
    private static function isNegated($index, $tokens) { //: bool
        $negated = false;

        $startIndex = $index - 1;
        if ($startIndex < 0) {
            return $negated;
        }

        for ($i = $startIndex; $i >= 0; $i--) {
            if ($tokens[$i]->isTypeOf(Tokens::NEGATION)) {
                $negated = !$negated;
            } else {
                break;
            }
        }

        return $negated;
    }

    /**
     * Normalize term (strip quotes)
     *
     * @param Token $token
     *
     * @return string
     */
    private static function normalizeTerm($token) {//: string
        $term = $token->getContent();

        if ($token->isTypeOf(Tokens::TERM_QUOTED)) {
            $term = preg_replace('/^"(.*)"$/', '$1', $term);
        } elseif ($token->isTypeOf(Tokens::TERM_QUOTED_SINGLE)) {
            $term = preg_replace('/^\'(.*)\'$/', '$1', $term);
        }

        return $term;
    }

    /**
     * @param int $index
     *
     * @return PartInterface
     */
    private static function getPart($parts, $index) { //: PartInterface
        if (isset($parts[$index])) {
            return $parts[$index];
        }

        throw new \OverflowException('Invalid part index');
    }

    /**
     * @return PartInterface|null
     */
    private static function getLastPart($parts) {
        if (!empty($parts)) {
            $keys = array_keys($parts);
            return $parts[$keys[count($keys) - 1]];
        }
    }

}
