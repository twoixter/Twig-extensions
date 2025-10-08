<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Tests\Extension;

use PHPUnit\Framework\TestCase;
use Twig\Compiler;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Extension\CoreExtension;
use Twig\Extensions\AsFilterExpression;
use Twig\Extensions\I18nExtension;
use Twig\Lexer;
use Twig\TwigFilter;
use Twig\Loader\ArrayLoader;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Nodes;
use Twig\Parser;
use Twig\Source;

class AsFilterTest extends TestCase
{
    public function testThrowsAtInvalidArgumentsWhenParsing()
    {
        $this->expectException(SyntaxError::class);

        $env = $this->getEnvironment();
        $lexer = new Lexer($env);
        $parser = new Parser($env);
        $parser->parse($lexer->tokenize(new Source('{{ user | as(name) }}', uniqid())));
    }

    /**
     * @dataProvider getTests
     */
    public function testAsFilterIsTransparentToCompilation($expression, $expected)
    {
        $compiler = new Compiler($this->getEnvironment());
        $expression->compile($compiler);

        $this->assertEquals($compiler->getSource(), $expected);
    }

    public function getEnvironment()
    {
        $env = new Environment(new ArrayLoader([]));
        $env->addExtension(new I18nExtension());

        return $env;
    }

    public function getTests()
    {
        $tests = [];

        $expr = new ConstantExpression('foo', 1);
        $node = $this->createFilter($expr, 'as', [new ConstantExpression('bar', 1)]);
        $tests[] = [$node, '"foo"'];

        $expr = new ConstantExpression('foo', 1);
        $node = $this->createFilter($expr, 'as', [new ConstantExpression('bar', 1)]);
        $node = $this->createFilter($node, 'upper');
        $tests[] = [$node, 'Twig\Extension\CoreExtension::upper($this->env->getCharset(), "foo")'];

        $expr = new ConstantExpression('foo', 1);
        $node = $this->createFilter($expr, 'upper');
        $node = $this->createFilter($node, 'as', [new ConstantExpression('bar', 1)]);
        $tests[] = [$node, 'Twig\Extension\CoreExtension::upper($this->env->getCharset(), "foo")'];

        return $tests;
    }

    protected function createFilter($node, $tag, array $arguments = [])
    {
        $arguments = new Nodes($arguments);
        $name = $tag == 'as'
            ? new TwigFilter($tag, null, [ "node_class" => AsFilterExpression::class ])
            // Taken from Twig\Extension\CoreExtension
            : new TwigFilter($tag, [CoreExtension::class, $tag], ['needs_charset' => true]);
        return $tag == 'as'
            ? new AsFilterExpression($node, $name, $arguments, 1)
            : new FilterExpression($node, $name, $arguments, 1);
    }
}
