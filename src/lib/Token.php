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

final class Token extends Getter {

    /**
     * @var int
     */
    private $token;

    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $line;

    /**
     * @var string
     */
    private $content;

    /**
     * @param int $token
     * @param int $line
     * @param string $content
     */
    public function __construct($token, $line, $content) {
        $this->token = $token;
        $this->line = $line;
        $this->content = $content;
        $this->name = Tokens::getName($token);
    }

    /**
     * @return int
     */
    public function getToken() {//: int
        return $this->token;
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

        return in_array($this->token, $token);
    }

    /**
     * @return string
     */
    public function getName() {//: string
        return $this->name;
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
    public function getContent() {//: string
        return $this->content;
    }

}
