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

use Twig\Environment;
use Twig\Extensions\I18nExtension;
use Twig\Lexer;
use Twig\Loader\ArrayLoader;
use Twig\NodeVisitor\OptimizerNodeVisitor;
use Twig\Parser;
use Twig\Source;
use Twig\Test\NodeTestCase;

class TransComplexTest extends NodeTestCase
{
    static $environment = null;

    const DEBUG_LINE = "// line 1\n";

    protected function getEnvironment()
    {
        return static::getEnvironmentForComplexTrans();
    }

    static protected function getEnvironmentForComplexTrans()
    {
        $environment = new Environment(new ArrayLoader([]), [
            'autoescape' => false,
            'optimizations' => OptimizerNodeVisitor::OPTIMIZE_NONE
        ]);
        $environment->addExtension(new I18nExtension([
            'complex_vars' => true
        ]));
        
        return $environment;
    }

    static public function provideTests(): \Traversable|array
    {
        $tests = [];

        // #0 Testing simple "complex" variables.
        $node = static::parseTrans('{% trans %}J\'ai {{user.pommes}}{% endtrans %}');
        $tests[] = [$node, self::DEBUG_LINE.'yield strtr(gettext("J\'ai %user_pommes%"), array("%user_pommes%" => ' . static::createAttributeGetter() . static::createVariableGetter('user') . ', "pommes", [], "any", false, false, false, 1), ));'];

        // #1 Testing somewhat more complex "complex" variables.
        $node = static::parseTrans('{% trans %}J\'ai {{user["first"].pommes}}{% endtrans %}');
        $tests[] = [$node, self::DEBUG_LINE.'yield strtr(gettext("J\'ai %user_first_pommes%"), array("%user_first_pommes%" => ' . static::createAttributeGetter() . '(($_v0 = ' . static::createVariableGetter('user') . ') && is_array($_v0) || $_v0 instanceof ArrayAccess ? ($_v0["first"] ?? null) : null), "pommes", [], "any", false, false, false, 1), ));'];

        // #2 Testing multiple complex fields gets same name.
        $node = static::parseTrans('{% trans %}Je suis {{user.name}} {{user.name}}{% endtrans %}');
        $tests[] = [$node, self::DEBUG_LINE.'yield strtr(gettext("Je suis %user_name% %user_name%"), array("%user_name%" => ' . static::createAttributeGetter() . static::createVariableGetter('user') . ', "name", [], "any", false, false, false, 1), ));'];

        // #3 Testing multiple complex fields gets different names.
        $node = static::parseTrans('{% trans %}Je suis {{user.name | upper}} {{user.name | lower}}{% endtrans %}');
        $tests[] = [$node, self::DEBUG_LINE.'yield strtr(gettext("Je suis %user_name% %user_name_2%"), array("%user_name%" => Twig\Extension\CoreExtension::upper($this->env->getCharset(), ' . static::createAttributeGetter() . static::createVariableGetter('user') . ', "name", [], "any", false, false, false, 1)), "%user_name_2%" => Twig\Extension\CoreExtension::lower($this->env->getCharset(), ' . static::createAttributeGetter() . static::createVariableGetter('user').', "name", [], "any", false, false, false, 1)), ));'];

        // #4 Testing plurals still works using complex algorithm.
        // NOTE: Notice the "\n" line breaks so that {{count}} variables are on different lines
        $node = static::parseTrans("{% trans %}\nI have {{count}} apple\n{% plural 12 %}\nI have {{count}} apples\n{% endtrans %}");
        $tests[] = [$node, self::DEBUG_LINE.'yield strtr(ngettext("I have %count% apple", "I have %count% apples", abs(12)), array("%count%" => abs(12), ));'];

        // #5 Testing plurals gets the same complex variable name and value.
        // NOTE: Notice the "\n" line breaks so that {{count}} variables are on different lines
        $node = static::parseTrans("{% trans %}\nHi {{user.name}}, i have {{count}} apple\n{% plural 12 %}\nHi {{user.name}} I have {{count}} apples\n{% endtrans %}");
        $tests[] = [$node, self::DEBUG_LINE.'yield strtr(ngettext("Hi %user_name%, i have %count% apple", "Hi %user_name% I have %count% apples", abs(12)), array("%user_name%" => ' . static::createAttributeGetter() . "// line 4\n" . static::createVariableGetter('user') . ', "name", [], "any", false, false, false, 4), "%count%" => abs(12), ));'];

        // #6 Testing plurals gets different complex variable names and values.
        $node = static::parseTrans('{% trans %}Sorry {{user.name}}, i only have one apple{% plural 12 %}YUP! {{user.name | upper}} I HAVE {{count}} APPLES{% endtrans %}');
        $tests[] = [$node, self::DEBUG_LINE.'yield strtr(ngettext("Sorry %user_name%, i only have one apple", "YUP! %user_name_2% I HAVE %count% APPLES", abs(12)), array("%user_name%" => ' . static::createAttributeGetter() . static::createVariableGetter('user').', "name", [], "any", false, false, false, 1), "%user_name_2%" => Twig\Extension\CoreExtension::upper($this->env->getCharset(), ' . static::createAttributeGetter() . static::createVariableGetter('user') . ', "name", [], "any", false, false, false, 1)), "%count%" => abs(12), ));'];        

        // #7 Testing plurals with different complex variable names and values works even with "as" filter.
        $node = static::parseTrans('{% trans %}Sorry {{user.name | as("name")}}, i only have one apple{% plural 12 %}YUP! {{user.name | upper | as("name")}} I HAVE {{count}} APPLES{% endtrans %}');
        $tests[] = [$node, self::DEBUG_LINE.'yield strtr(ngettext("Sorry %name%, i only have one apple", "YUP! %name_2% I HAVE %count% APPLES", abs(12)), array("%name%" => ' . static::createAttributeGetter() . static::createVariableGetter('user').', "name", [], "any", false, false, false, 1), "%name_2%" => Twig\Extension\CoreExtension::upper($this->env->getCharset(), ' . static::createAttributeGetter() . static::createVariableGetter('user').', "name", [], "any", false, false, false, 1)), "%count%" => abs(12), ));'];

        return $tests;
    }

    /**
     * Parses a Twig source. Returns the parsed Trans Node tree so that we
     * don't have to recreate it manually using new Twig_Node_Trans(...), etc.
     *
     * @param string $source The template source.
     *
     * @return Twig_Node_Module The parsed template.
     */
    static private function parseTrans($source)
    {
        static $environment = null;

        if (is_null($environment)) {
            $environment = static::getEnvironmentForComplexTrans();
        }

        $lexer = new Lexer($environment);
        $parser = new Parser($environment);
        $module = $parser->parse($lexer->tokenize(new Source($source, uniqid())));

        // The Twig_Extensions_Node_Trans we look for is the first (and only)
        // node of the Twig_Node_Body. If it's not, some error will be thrown
        // along the test chain. :-)
        return $module->getNode('body')->getNode(0);
    }
}
