<?php

namespace Laminas\I18n\Translator\Plural;

use Laminas\I18n\Exception;

use function abs;
use function floor;
use function preg_match;
use function sprintf;

/**
 * Plural rule evaluator.
 *
 * @final
 */
class Rule
{
    /**
     * Parser instance.
     *
     * @var Parser
     */
    protected static $parser;

    /**
     * Abstract syntax tree.
     *
     * @var array
     */
    protected $ast;

    /**
     * Create a new plural rule.
     *
     * @param int   $numPlurals
     * @param array $ast
     */
    protected function __construct(
        /**
         * Number of plurals in this rule.
         */
        protected $numPlurals,
        array $ast
    ) {
        $this->ast = $ast;
    }

    /**
     * Evaluate a number and return the plural index.
     *
     * @param  int $number
     * @return int
     * @throws Exception\RangeException
     */
    public function evaluate($number)
    {
        $result = $this->evaluateAstPart($this->ast, abs((int) $number));

        if ($result < 0 || $result >= $this->numPlurals) {
            throw new Exception\RangeException(
                sprintf('Calculated result %s is between 0 and %d', $result, $this->numPlurals - 1)
            );
        }

        return $result;
    }

    /**
     * Get number of possible plural forms.
     *
     * @return int
     */
    public function getNumPlurals()
    {
        return $this->numPlurals;
    }

    /**
     * Evaluate a part of an ast.
     *
     * @param  array $ast
     * @param  int   $number
     * @return int
     * @throws Exception\ParseException
     */
    protected function evaluateAstPart(array $ast, $number)
    {
        switch ($ast['id']) {
            case 'number':
                return $ast['arguments'][0];

            case 'n':
                return $number;

            case '+':
                return $this->evaluateAstPart($ast['arguments'][0], $number)
                       + $this->evaluateAstPart($ast['arguments'][1], $number);

            case '-':
                return $this->evaluateAstPart($ast['arguments'][0], $number)
                       - $this->evaluateAstPart($ast['arguments'][1], $number);

            case '/':
                // Integer division
                return floor(
                    $this->evaluateAstPart($ast['arguments'][0], $number)
                    / $this->evaluateAstPart($ast['arguments'][1], $number)
                );

            case '*':
                return $this->evaluateAstPart($ast['arguments'][0], $number)
                       * $this->evaluateAstPart($ast['arguments'][1], $number);

            case '%':
                return $this->evaluateAstPart($ast['arguments'][0], $number)
                       % $this->evaluateAstPart($ast['arguments'][1], $number);

            case '>':
                return $this->evaluateAstPart($ast['arguments'][0], $number)
                       > $this->evaluateAstPart($ast['arguments'][1], $number)
                       ? 1 : 0;

            case '>=':
                return $this->evaluateAstPart($ast['arguments'][0], $number)
                       >= $this->evaluateAstPart($ast['arguments'][1], $number)
                       ? 1 : 0;

            case '<':
                return $this->evaluateAstPart($ast['arguments'][0], $number)
                       < $this->evaluateAstPart($ast['arguments'][1], $number)
                       ? 1 : 0;

            case '<=':
                return $this->evaluateAstPart($ast['arguments'][0], $number)
                       <= $this->evaluateAstPart($ast['arguments'][1], $number)
                       ? 1 : 0;

            case '==':
                // @codingStandardsIgnoreStart
                return $this->evaluateAstPart($ast['arguments'][0], $number)
                       == $this->evaluateAstPart($ast['arguments'][1], $number)
                       ? 1 : 0;

            case '!=':
                return $this->evaluateAstPart($ast['arguments'][0], $number)
                       != $this->evaluateAstPart($ast['arguments'][1], $number)
                       ? 1 : 0;
                // @codingStandardsIgnoreEnd

            case '&&':
                return $this->evaluateAstPart($ast['arguments'][0], $number)
                       && $this->evaluateAstPart($ast['arguments'][1], $number)
                       ? 1 : 0;

            case '||':
                return $this->evaluateAstPart($ast['arguments'][0], $number)
                       || $this->evaluateAstPart($ast['arguments'][1], $number)
                       ? 1 : 0;

            case '!':
                return ! $this->evaluateAstPart($ast['arguments'][0], $number)
                       ? 1 : 0;

            case '?':
                return $this->evaluateAstPart($ast['arguments'][0], $number)
                       ? $this->evaluateAstPart($ast['arguments'][1], $number)
                       : $this->evaluateAstPart($ast['arguments'][2], $number);

            default:
                throw new Exception\ParseException(sprintf(
                    'Unknown token: %s',
                    $ast['id']
                ));
        }
    }

    /**
     * Create a new rule from a string.
     *
     * @param  string $string
     * @throws Exception\ParseException
     * @return Rule
     */
    public static function fromString($string)
    {
        if (static::$parser === null) {
            static::$parser = new Parser();
        }

        if (! preg_match('(nplurals=(?P<nplurals>\d+))', $string, $match)) {
            throw new Exception\ParseException(sprintf(
                'Unknown or invalid parser rule: %s',
                $string
            ));
        }

        $numPlurals = (int) $match['nplurals'];

        if (! preg_match('(plural=(?P<plural>[^;\n]+))', $string, $match)) {
            throw new Exception\ParseException(sprintf(
                'Unknown or invalid parser rule: %s',
                $string
            ));
        }

        $tree = static::$parser->parse($match['plural']);
        $ast  = static::createAst($tree);

        return new static($numPlurals, $ast);
    }

    /**
     * Create an AST from a tree.
     *
     * Theoretically we could just use the given Symbol, but that one is not
     * so easy to serialize and also takes up more memory.
     *
     * @return array
     */
    protected static function createAst(Symbol $symbol)
    {
        $ast = ['id' => $symbol->id, 'arguments' => []];

        switch ($symbol->id) {
            case 'n':
                break;

            case 'number':
                $ast['arguments'][] = $symbol->value;
                break;

            case '!':
                $ast['arguments'][] = static::createAst($symbol->first);
                break;

            case '?':
                $ast['arguments'][] = static::createAst($symbol->first);
                $ast['arguments'][] = static::createAst($symbol->second);
                $ast['arguments'][] = static::createAst($symbol->third);
                break;

            default:
                $ast['arguments'][] = static::createAst($symbol->first);
                $ast['arguments'][] = static::createAst($symbol->second);
                break;
        }

        return $ast;
    }
}
