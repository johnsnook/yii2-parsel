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

/**
 * A collection of Tokens
 *
 * @see [[Token]]
 */
final class Tokens {

    const WHITESPACE = 0;
    const BRACE_OPEN = 1;
    const BRACE_CLOSE = 2;
    const KEYWORD = 3;
    const NEGATION = 4;
    const TERM = 5;
    const TERM_QUOTED = 6;
    const TERM_QUOTED_SINGLE = 7;
    const FIELD_TERM = 8;
    const FIELD_TERM_QUOTED = 9;
    const FIELD_TERM_QUOTED_SINGLE = 10;
    const FULL_MATCH = 11;

    /**
     * Get a token name from its value
     *
     * @param int $token
     *
     * @return string
     */
    public static function getName($token) {//: string
        $tokens = self::getTokenMapping();

        if (!isset($tokens[$token])) {
            throw new \InvalidArgumentException(sprintf('Token with value "%d" was not found', $token));
        }

        return $tokens[$token];
    }

    /**
     * Get tokens identifying term values
     *
     * @return array
     */
    public static function getTermTokens() {//: array
        static $termTokens;

        if (null === $termTokens) {
            $termTokens = [
                self::TERM,
                self::TERM_QUOTED,
                self::TERM_QUOTED_SINGLE,
                self::FIELD_TERM,
                self::FIELD_TERM_QUOTED,
                self::FIELD_TERM_QUOTED_SINGLE
            ];
        }

        return $termTokens;
    }

    /**
     * I didn't write this and am too tired to figure out what it's doing right now
     *
     * @staticvar array $constants
     * @return array 
     */
    private static function getTokenMapping() {//: array
        static $constants;

        if (is_null($constants)) {
            $reflection = new \ReflectionClass(__CLASS__);
            $constants = array_flip($reflection->getConstants());
        }

        return $constants;
    }

}
