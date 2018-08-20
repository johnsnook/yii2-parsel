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
 * Represents a single lexeme token
 *
 * @property-read int $type The type of token as defined in [[Tokens]]
 * @property-read string $name Human readable type name
 * @property-read string $field If token has field definition, the fields name
 * @property-read int $line Not sure what this is for
 * @property-read string $value The tokens contents
 */
final class Token extends Getter {

    /**
     * @var int
     */
    private $type;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $field;

    /**
     * @var int
     */
    private $line;

    /**
     * @var string
     */
    private $value;

    /**
     * @param int $type
     * @param int $line
     * @param string $value
     */
    public function __construct($type, $line, $value) {
        $this->type = $type;
        $this->line = $line;
        $this->value = $value;

        /**
         * 1) See if a colon is in the term
         * 2) Split the term and set the field to the leftmost split term value
         * 3) set the value to the right side of the original term
         */
        if ($this->isTypeOf([
                    Tokens::FIELD_TERM,
                    Tokens::FIELD_TERM_QUOTED,
                    Tokens::FIELD_TERM_QUOTED_SINGLE])) {
            list($this->field, $this->value) = explode(':', $this->value);
        }
        $this->name = Tokens::getName($type);
    }

    /**
     * @return int
     */
    public function getType() {//: int
        return $this->type;
    }

    /**
     * @param int|array $token
     *
     * @return bool
     */
    public function isTypeOf($token) {//: bool
        if (!is_array($token)) {
            $token = [$token];
        }

        return in_array($this->type, $token);
    }

    /**
     * @return string
     */
    public function getName() {//: string
        return $this->name;
    }

    /**
     * @return string
     */
    public function getField() {//: string
        return $this->field;
    }

    /**
     * @return int
     */
    public function getLine() {//: int
        return $this->line;
    }

    /**
     * @return string
     */
    public function getValue() {//: string
        return $this->value;
    }

}
