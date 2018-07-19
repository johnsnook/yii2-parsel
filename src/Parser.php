<?php

namespace johnsnook\parsel;

//use johnsnook\parsel\part\Keyword;
//use johnsnook\parsel\part\Query;
//use johnsnook\parsel\part\Term;

class Parser {

    const QUOTE_NONE = 0;
    const QUOTE_SINGLE = 1;
    const QUOTE_DOUBLE = 2;

    /**
     * @param Token[] $tokens
     *
     * @return Query
     */
    public static function parse($tokens) {#: Query
        //$query = new Query();

        /** @var Query[] $queryStack */
        $queryStack = [];
        $currentQuery = [];

        /** @var Token $previousToken */
        $previousToken = null;

        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if (!$token instanceof Token) {
                throw new \InvalidArgumentException(sprintf(
                        'Token at index %d must be of type Token, "%s" given', $i, is_object($token) ? get_class($token) : gettype($token)
                ));
            }
            // brace open/close - sub-queries
            if ($token->isTypeOf(Tokens::T_BRACE_OPEN)) {
                array_push($queryStack, $currentQuery);

                $negated = self::isNegated($i, $tokens);
                $currentQuery = ['type' => 'query', 'negated' => $negated, 'items' => []];
            }

            if ($token->isTypeOf(Tokens::T_BRACE_CLOSE)) {
                if (count($queryStack) === 0) {
                    throw new ParserException('Can\'t close sub query as query stack is empty');
                }

                $closingQuery = $currentQuery;
                $currentQuery = array_pop($queryStack);
                $currentQuery[] = $closingQuery;
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

                // add an AND/OR before inserting the term if the last part was no keyword
                $lastPart = self::getLastPart($currentQuery);

                if (isset($lastPart['type']) && ($lastPart['type'] !== 'keyword')) {
                    if (count($queryStack) > 0) {
                        $currentQuery['items'][] = ['type' => 'keyword', 'value' => 'AND'];
                    } else {
                        $currentQuery[] = ['type' => 'keyword', 'value' => 'AND'];
                    }
                }

                $lastPart = self::getLastPart($currentQuery);
                if (isset($lastPart['type']) && ($lastPart['type'] !== 'keyword')) {
                    throw new ParserException(sprintf(
                            'Expected a keyword (AND/OR), but found a %s', $lastPart['type']
                    ));
                }
                if (count($queryStack) > 0) {
                    $currentQuery['items'][] = $term;
                } else {
                    $currentQuery[] = $term;
                }
            }
            if ($token->isTypeOf(Tokens::T_KEYWORD)) {
                if ($previousToken && $previousToken->isTypeOf(Tokens::T_KEYWORD)) {
                    throw new ParserException(sprintf(
                            'Keyword can\'t be succeeded by another keyword (%s %s)', $previousToken->getContent(), $token->getContent()
                    ));
                }
                if (count($queryStack) > 0) {
                    $currentQuery['items'][] = ['type' => 'keyword', 'value' => $token->getContent()];
                } else {
                    $currentQuery[] = ['type' => 'keyword', 'value' => $token->getContent()];
                }
            }
            $previousToken = $token;
        }
        return $currentQuery;
    }

    /**
     * Check if the token is fuzzy (is not quoted and contains *)
     *
     * @param Token $token
     * @param string $value
     *
     * @return bool
     */
    private static function isFuzzy($token, $value) { #: bool
        if ($token->isTypeOf([Tokens::T_TERM_QUOTED, Tokens::T_TERM_QUOTED_SINGLE])) {
            return false;
        }
        return false !== (strpos($value, '*') || strpos($value, '?'));
    }

    /**
     * Check if expression was negated by looking back at previous tokens
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
            if ($tokens[$i]->isTypeOf(Tokens::T_FULL_MATCH)) {
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
            if ($tokens[$i]->isTypeOf(Tokens::T_NEGATION)) {
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

        if ($token->isTypeOf(Tokens::T_TERM_QUOTED)) {
            $term = preg_replace('/^"(.*)"$/', '$1', $term);
        } elseif ($token->isTypeOf(Tokens::T_TERM_QUOTED_SINGLE)) {
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
//        $my_arr[$keys[1]] = "not so much bling";

        if (!empty($parts)) {
            //dump($parts);
            $keys = array_keys($parts);
            return $parts[$keys[count($keys) - 1]];
        }
    }

}
