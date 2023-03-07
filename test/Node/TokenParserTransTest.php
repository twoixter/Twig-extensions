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
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Extensions\I18nExtension;
use Twig\Loader\ArrayLoader;
use Twig\Lexer;
use Twig\Parser;
use Twig\Source;
use Twig\Node\ModuleNode;

class TokenParserTransTest extends TestCase
{
    private $simple_env;
    private $complex_env;

    public function setUp(): void
    {
        $this->simple_env = new Environment(new ArrayLoader(array()));
        $this->simple_env->addExtension(new I18nExtension());

        $this->complex_env = new Environment(new ArrayLoader(array()));
        $this->complex_env->addExtension(new I18nExtension(['complex_vars' => true]));
    }

    /**
     * Test original behaviour is preserved. Simple vars are parsed.
     */
    public function testParsesSimpleVars()
    {
        $parsed = $this->parseSource('{% trans %}Hey {{name}}!{% endtrans %}', $this->simple_env);

        // Kind of dummy assertion. Since exception is not thrown,test already passes.
        $this->assertInstanceOf(ModuleNode::class, $parsed);
    }

    /**
     * Test original behaviour is preserved. Complex vars throws.
     */
    public function testDoesNotParseComplexVars()
    {
        $this->expectException(SyntaxError::class);

        $parsed = $this->parseSource('{% trans %}Hey {{user.name}}!{% endtrans %}', $this->simple_env);
    }

    /**
     * Complex vars are parsed when 'complex_vars' config is enabled.
     */
    public function testDoesNotThrowForComplexVarsWhenActive()
    {
        $parsed = $this->parseSource('{% trans %}Hey {{user.name}}!{% endtrans %}', $this->complex_env);

        // Kind of dummy assertion. Since exception is not thrown,test already passes.
        $this->assertInstanceOf(ModuleNode::class, $parsed);
    }

    /**
     * Parses a Twig source. Returns the parsed Node tree.
     *
     * @param string           $source      The template source.
     * @param Twig_Environment $environment The environment to use.
     *
     * @return Twig_Node_Module The parsed template.
     */
    private function parseSource($source, $environment)
    {
        $lexer = new Lexer($environment);
        $parser = new Parser($environment);
        return $parser->parse($lexer->tokenize(new Source($source, uniqid())));
    }

}
