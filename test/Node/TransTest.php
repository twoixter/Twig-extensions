<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Tests\Extension\Node;

use Twig\Runtime\EscaperRuntime;
use Twig\Extensions\Node\TransNode;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\Variable\ContextVariable;  // Was Twig\Node\Expression\NameExpression
use Twig\Node\Nodes;
use Twig\Node\PrintNode;
use Twig\Node\TextNode;
use Twig\TwigFilter;
use Twig\Test\NodeTestCase;

class TransTest extends NodeTestCase
{
    public function testConstructor()
    {
        $count = new ConstantExpression(12, 0);
        $body = new Nodes([
            new TextNode('Hello', 0),
        ], 0);
        $plural = new Nodes([
            new TextNode('Hey ', 0),
            new PrintNode(new ContextVariable('name', 0), 0),
            new TextNode(', I have ', 0),
            new PrintNode(new ContextVariable('count', 0), 0),
            new TextNode(' apples', 0),
        ], 0);
        $node = new TransNode($body, $plural, $count, null, 0);

        $this->assertEquals($body, $node->getNode('body'));
        $this->assertEquals($count, $node->getNode('count'));
        $this->assertEquals($plural, $node->getNode('plural'));
    }

    static public function provideTests(): \Traversable|array
    {
        $tests = [];

        $body = new ContextVariable('foo', 0);
        $node = new TransNode($body, null, null, null, 0);
        $tests[] = [$node, sprintf('yield gettext(%s);', static::createVariableGetter('foo'))];

        $body = new ConstantExpression('Hello', 0);
        $node = new TransNode($body, null, null, null, 0);
        $tests[] = [$node, 'yield gettext("Hello");'];

        $body = new Nodes([
            new TextNode('Hello', 0),
        ], 0);
        $node = new TransNode($body, null, null, null, 0);
        $tests[] = [$node, 'yield gettext("Hello");'];

        $body = new Nodes([
            new TextNode('J\'ai ', 0),
            new PrintNode(new ContextVariable('foo', 0), 0),
            new TextNode(' pommes', 0),
        ], 0);
        $node = new TransNode($body, null, null, null, 0);
        $tests[] = [$node, sprintf('yield strtr(gettext("J\'ai %%foo%% pommes"), array("%%foo%%" => %s, ));', static::createVariableGetter('foo'))];

        $count = new ConstantExpression(12, 0);
        $body = new Nodes([
            new TextNode('Hey ', 0),
            new PrintNode(new ContextVariable('name', 0), 0),
            new TextNode(', I have one apple', 0),
        ], 0);
        $plural = new Nodes([
            new TextNode('Hey ', 0),
            new PrintNode(new ContextVariable('name', 0), 0),
            new TextNode(', I have ', 0),
            new PrintNode(new ContextVariable('count', 0), 0),
            new TextNode(' apples', 0),
        ], 0);
        $node = new TransNode($body, $plural, $count, null, 0);
        $tests[] = [$node, sprintf('yield strtr(ngettext("Hey %%name%%, I have one apple", "Hey %%name%%, I have %%count%% apples", abs(12)), array("%%name%%" => %s, "%%name%%" => %s, "%%count%%" => abs(12), ));', static::createVariableGetter('name'), static::createVariableGetter('name'))];
/*                                 'yield strtr(ngettext("Hey %%name%%, I have one apple", "Hey %%name%%, I have %%count%% apples", abs(12)), array("%%name%%" => ($context["name"] ?? null), "%%name%%" => ($context["name"] ?? null), "%%name%%" => ($context["name"] ?? null), "%%count%%" => abs(12), ));'
*/

        // with escaper extension set to on
        $body = new Nodes([
            new TextNode('J\'ai ', 0),
            new PrintNode(new FilterExpression(new ContextVariable('foo', 0), new TwigFilter('escape', [EscaperRuntime::class, 'escape'], ['is_safe_callback' => [EscaperRuntime::class, 'escapeFilterIsSafe']]), new Nodes(), 0), 0),
            new TextNode(' pommes', 0),
        ], 0);

        $node = new TransNode($body, null, null, null, 0);
        $tests[] = [$node, sprintf('yield strtr(gettext("J\'ai %%foo%% pommes"), array("%%foo%%" => %s, ));', static::createVariableGetter('foo'))];

        // with notes
        $body = new ConstantExpression('Hello', 0);
        $notes = new TextNode('Notes for translators', 0);
        $node = new TransNode($body, null, null, $notes, 0);
        $tests[] = [$node, "// notes: Notes for translators\nyield gettext(\"Hello\");"];

        $body = new ConstantExpression('Hello', 0);
        $notes = new TextNode("Notes for translators\nand line breaks", 0);
        $node = new TransNode($body, null, null, $notes, 0);
        $tests[] = [$node, "// notes: Notes for translators and line breaks\nyield gettext(\"Hello\");"];

        $count = new ConstantExpression(5, 0);
        $body = new TextNode('There is 1 pending task', 0);
        $plural = new Nodes([
            new TextNode('There are ', 0),
            new PrintNode(new ContextVariable('count', 0), 0),
            new TextNode(' pending tasks', 0),
        ], 0);
        $notes = new TextNode('Notes for translators', 0);
        $node = new TransNode($body, $plural, $count, $notes, 0);
        $tests[] = [$node, "// notes: Notes for translators\n".'yield strtr(ngettext("There is 1 pending task", "There are %count% pending tasks", abs(5)), array("%count%" => abs(5), ));'];

        return $tests;
    }
}
