<?php

namespace johnsnook\parsel\lib;

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

/**
 * Deconstructs a user query string into lexemes as an array of tokens to be
 * parsed by the parser.
 */
class Lexer {

    /**
     * @var PhlexyLexer The PhlexyLexer object, which does the heavy lifting
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
     * Defines the character to token relationship to be used by regex
     *
     * @return array
     */
    protected function getDefaultDefinition() { #: array
        return [
            '\(' => Tokens::BRACE_OPEN,
            '\)' => Tokens::BRACE_CLOSE,
            #'(AND|OR)' => Tokens::KEYWORD,
            '(?<![A-Z])(AND|OR)(?![A-Z])' => Tokens::KEYWORD,
            '-' => Tokens::NEGATION,
            '=' => Tokens::FULL_MATCH,
            '[^\s!\(\)]+:"[^"]+"' => Tokens::FIELD_TERM_QUOTED,
            "[^\s!\(\)]+:'[^']+'" => Tokens::FIELD_TERM_QUOTED_SINGLE,
            '[^\s!\(\)]+:[^\s!\(\)]+' => Tokens::FIELD_TERM,
            '"[^"]+"' => Tokens::TERM_QUOTED,
            "'[^']+'" => Tokens::TERM_QUOTED_SINGLE,
            '[^\s!\(\)]+' => Tokens::TERM,
            '\s+' => Tokens::WHITESPACE,
        ];
    }

    /**
     * Gets the data generator in a regex wrapper, gets our definition then lexes.
     *
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
     * Outer wrapper of the lexer object.  Removes the extraneous whitespace tokens
     *
     * @param $string
     *
     * @return Token[]
     */
    public function lex($string) {#: array
        $tokens = $this->lexer->lex($string);
//        dump($tokens);
//        die();
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
