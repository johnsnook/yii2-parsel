<?php

namespace johnsnook\parsel;

/**
 * This file is part of the Yii2 extension module, yii2-parsel.
 * It's been heavily modified from the original by pimcore
 * @see https://github.com/pimcore/search-query-parser
 *
 * @author John Snook
 * @date 2018-07-28
 * @license https://github.com/johnsnook/yii2-parsel/LICENSE
 * @copyright 2018 John Snook Consulting
 */
use Phlexy\Lexer as PhlexyLexer;
use Phlexy\LexerDataGenerator;
use Phlexy\LexerFactory\Stateless\UsingPregReplace;

class Lexer {

    /**
     * @var PhlexyLexer
     */
    protected $lexer;

    /**
     * @param PhlexyLexer|null $lexer
     */
    public function __construct(PhlexyLexer $lexer = null) {
        if (null === $lexer) {
            $this->lexer = $this->buildDefaultLexer();
        } else {
            $this->lexer = $lexer;
        }
    }

    /**
     * @return array
     */
    protected function getDefaultDefinition() { #: array
        return [
            '\(' => Tokens::BRACE_OPEN,
            '\)' => Tokens::BRACE_CLOSE,
            '(AND|OR)' => Tokens::KEYWORD,
            '-' => Tokens::NEGATION,
            '=' => Tokens::FULL_MATCH,
            '"[^"]+"' => Tokens::TERM_QUOTED,
            "'[^']+'" => Tokens::TERM_QUOTED_SINGLE,
            '[^\s!\(\)]+' => Tokens::TERM,
            '\s+' => Tokens::WHITESPACE,
        ];
    }

    /**
     * @return PhlexyLexer
     */
    protected function buildDefaultLexer() { #: PhlexyLexer
        $factory = new UsingPregReplace(
                new LexerDataGenerator()
        );

        $definition = $this->getDefaultDefinition();

        // The "i" is an additional modifier (all createLexer methods accept it)
        return $factory->createLexer($definition, 'i');
    }

    /**
     * @param $string
     *
     * @return Token[]
     */
    public function lex($string) {#: array
        $tokens = $this->lexer->lex($string);

        // ignore whitespace
        $tokens = array_filter($tokens, function ($token) {
            return $token[0] !== Tokens::WHITESPACE;
        });

        // transform arrays into token objects
        /** @var Token[] $tokens */
        $tokens = array_map(function (array $token) {
            return new Token($token[0], $token[1], $token[2]);
        }, $tokens);

        // make sure we return a numerically indexed array
        return array_values($tokens);
    }

}
