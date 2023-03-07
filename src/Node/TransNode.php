<?php

/*
 * This file is part of Twig.
 *
 * (c) 2010-2019 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Extensions\Node;

use Twig\Compiler;
use Twig\Error\SyntaxError;
use Twig\Extensions\AsFilterExpression;
use Twig\Node\CheckToStringNode;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Expression\TempNameExpression;
use Twig\Node\Node;
use Twig\Node\PrintNode;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TransNode extends Node
{
    /**
     * Holds the current options from I18n extension.
     *
     * @see Twig\Extensions\Node\TransNodeOptions
     */
    private $options;

    public function __construct(
        Node $body,
        Node $plural = null,
        AbstractExpression $count = null,
        Node $notes = null,
        $lineno,
        $tag = null,
        TransNodeOptions $options = null
    ) {
        $nodes = ['body' => $body];
        if (null !== $count) {
            $nodes['count'] = $count;
        }
        if (null !== $plural) {
            $nodes['plural'] = $plural;
        }
        if (null !== $notes) {
            $nodes['notes'] = $notes;
        }
        if (null !== $options) {
            $this->options = $options;
        } else {
            $this->options = new TransNodeOptions();
        }

        parent::__construct($nodes, [], $lineno, $tag);
    }

    public function compile(Compiler $compiler)
    {
        $compiler->addDebugInfo($this);

        $delims = $this->options->getDelimiters();
        $complex = $this->options->getComplexVars();

        $vars = [];
        list($msg, $vars) = $this->compileString($this->getNode('body'), $vars);

        if ($this->hasNode('plural')) {
            list($msg1, $vars1) = $this->compileString($this->getNode('plural'), $vars);

            $vars = array_merge($vars, $vars1);
        }

        $function = $this->getTransFunction($this->hasNode('plural'));

        if ($this->hasNode('notes')) {
            $message = trim($this->getNode('notes')->getAttribute('data'));

            // line breaks are not allowed cause we want a single line comment
            $message = str_replace(["\n", "\r"], ' ', $message);
            $compiler->write("// notes: {$message}\n");
        }

        if ($vars) {
            $compiler
                ->write('echo strtr('.$function.'(')
                ->subcompile($msg)
            ;

            if ($this->hasNode('plural')) {
                $compiler
                    ->raw(', ')
                    ->subcompile($msg1)
                    ->raw(', abs(')
                    ->subcompile($this->hasNode('count') ? $this->getNode('count') : null)
                    ->raw(')')
                ;
            }

            $compiler->raw('), array(');

            foreach ($vars as $name => $var) {
                if (!$complex) {
                    $name = $var->getAttribute('name');
                }

                if (('count' === $name) && $this->hasNode('plural')) {
                    $compiler
                        ->string($delims[0] . 'count' . $delims[1])
                        ->raw(' => abs(')
                        ->subcompile($this->hasNode('count') ? $this->getNode('count') : null)
                        ->raw('), ')
                    ;
                } else {
                    $compiler
                        ->string($delims[0] . $name . $delims[1])
                        ->raw(' => ')
                        ->subcompile($var)
                        ->raw(', ')
                    ;
                }
            }

            $compiler->raw("));\n");
        } else {
            $compiler
                ->write('echo '.$function.'(')
                ->subcompile($msg)
            ;

            if ($this->hasNode('plural')) {
                $compiler
                    ->raw(', ')
                    ->subcompile($msg1)
                    ->raw(', abs(')
                    ->subcompile($this->hasNode('count') ? $this->getNode('count') : null)
                    ->raw(')')
                ;
            }

            $compiler->raw(");\n");
        }
    }

    private function compileString(Node $body, array $vars): array
    {
        if ($body instanceof NameExpression || $body instanceof ConstantExpression || $body instanceof TempNameExpression) {
            return [$body, []];
        }

        if (\count($body)) {
            $delims = $this->options->getDelimiters();
            $complex = $this->options->getComplexVars();
            $msg = '';

            // If not using $complex variables, we need to clear $vars array
            // because the normal flow without $complex uses $vars[] to add
            // elements to the array. If we don't clear $vars here there will
            // be a variable leak to the next strings in a "plural" setup.
            if (!$complex) {
                $vars = [];
            }

            foreach ($body as $node) {
                if ($node instanceof PrintNode) {
                    if ($complex) {
                        $expr = $node->getNode('expr');
                        $name = $this->guessNameFromExpression($expr);
                        $unique = $this->makeUnique($vars, $name, $expr);

                        $msg .= sprintf('%s%s%s', $delims[0], $unique, $delims[1]);
                        $vars[$unique] = $expr;
                    } else {
                        $n = $node->getNode('expr');
                        while ($n instanceof FilterExpression) {
                            $n = $n->getNode('node');
                        }
                        while ($n instanceof CheckToStringNode) {
                            $n = $n->getNode('expr');
                        }
                        $msg .= sprintf('%s%s%s', $delims[0], $n->getAttribute('name'), $delims[1]);
                        $vars[] = new NameExpression($n->getAttribute('name'), $n->getTemplateLine());
                    }
                } else {
                    $msg .= $node->getAttribute('data');
                }
            }
        } else {
            $msg = $body->getAttribute('data');
        }

        return [new Node([new ConstantExpression($this->options->getNormalize() ? $this->normalizeString($msg) : trim($msg), $body->getTemplateLine())]), $vars];
    }

    /**
     * Normalizes a string (removes spaces inside the string).
     *
     * Why?: For large translatable strings or strings spanning multiple lines,
     * it is necessary to normalize the string so that translators doesn't get
     * carriage returns or tab characters inside the string. Normalization is
     * also needed to mantain the integrity of multiline strings and the
     * indentation of the source file changed. Take for example:
     *
     *  Before (A):
     *
     *      {% trans %}
     *          This is a translatable string
     *          spanning multiple lines
     *      {% endtrans %}
     *
     *  After (B):
     *
     *      {% if some_condition %}
     *          {% trans %}
     *              This is a translatable string
     *              spanning multiple lines
     *          {% endtrans %}
     *      {% endif %}
     *
     * If the above example, we needed to add some condition and thus added
     * indentation because of the "if". Then, the translatable string changes
     * and to Gettext it is a different string because of the different spaces
     * inside the sentence.
     *
     * @param string $msg  The message string to be normalized.
     * @param string $glue The character used to replace spaces.
     *
     * @return string The normalized string.
     */
    protected function normalizeString($msg, $glue = ' ')
    {
        return preg_replace('/\s+/u', $glue, trim($msg));
    }

    /**
     * Guesses a name from a Twig\Node\Expression. First, we try to get the
     * propossed name from a 'as("name")' filter. If there is no 'as' filter,
     * try to guess a name from the expression itself.
     *
     * @param Twig\Node $expr Expression to guess name from.
     *
     * @return string Proposed name.
     */
    protected function guessNameFromExpression(Node $expr)
    {
        // Traverse the Expression AST tree trying to guess a name from 'as'
        // filters, until we reach a non-filter expression.
        while ($expr instanceof FilterExpression) {
            // OK, we are still inside a filter, let's look if we got a 'as'.
            if ($expr instanceof AsFilterExpression) {
                // 'as' filters always have to have at least one argument and
                // it is a Constant Expression, guarranteed by the parser.
                return $expr->getNode('arguments')->getNode(0)->getAttribute('value');
            }
            $expr = $expr->getNode('node');
        }

        // We are now at the AST tree for the main expression. We'll try to
        // mangle the name from the rest of this nodes.
        return implode('_', $this->extractNames($expr));
    }

    /**
     * Guesses a name from a series of Twig_Expression nodes. If the expression
     * is too complex to guess a name, it throws a Twig\Error\SyntaxError.
     *
     * @param Twig\Node $node The starting node to extract names from.
     *
     * @return array Array of names guessed up to this node.
     *
     * @throws Twig\Error\SyntaxError
     */
    protected function extractNames(Node $node)
    {
        if ($node instanceof GetAttrExpression) {
            return array_merge(
                $this->extractNames($node->getNode('node')),
                $this->extractNames($node->getNode('attribute'))
            );
        }

        if (($node instanceof NameExpression) || ($node instanceof TempNameExpression)) {
            return [$node->getAttribute('name')];
        }

        if ($node instanceof ConstantExpression) {
            // Constants may have spaces in it. Normalize it!
            return [$this->normalizeString($node->getAttribute('value'), '_')];
        }

        throw new SyntaxError('Sorry, the expression is too complex to use as "trans" variable as is. Please consider using an "as" filter.', $node->getTemplateLine());
    }

    /**
     * Makes a variable name unique by adding a serial number if the variable
     * name already exists and its expressions are different. In other words,
     * we only add serial numbers to variables who uses different filters, etc.
     *
     * @param array     $vars The existing variables array
     * @param string    $name The proposed new name
     * @param Twig\Node $expr The expression for this variable
     *
     * @return string The new unique name
     */
    protected function makeUnique(array $vars, $name, Node $expr)
    {
        // Loop through until we get a free name. Note that the starting index
        // is "2" instead of "1". This gets us a good looking name series like
        // "name", "name_2",... It would be ugly to have "name" and "name_1"
        $index = 2;
        $new_name = $name;
        while (array_key_exists($new_name, $vars) && (!$this->isNodeSimilar($vars[$new_name], $expr))) {
            $new_name = $name.'_'.$index++;
        }

        return $new_name;
    }

    /**
     * Checks that two nodes are similar. This includes deep comparing their
     * child nodes. Two nodes are similar if they both share the same tags,
     * attributes and their child nodes are also similar.
     *
     * This explicity leaves out the "lineno" attribute and the "specialVars"
     * who in the context of the {%trans%} tag they must be equal.
     *
     * @param Twig\Node $first  The first node, source of the comparison
     * @param Twig\Node $second The second node to compare against
     *
     * @return bool Returns True if both Nodes seems similar
     */
    protected function isNodeSimilar(Node $first, Node $second)
    {
        // First sign that the Nodes are not even similar. Fail fast...
        if (
            ($first->tag != $second->tag) ||
            ($first->attributes != $second->attributes) ||
            ($first->count() != $second->count())
        ) {
            return false;
        }

        // Iterate each node for similarity
        foreach ($first->nodes as $key => $value) {
            if (!$second->hasNode($key)) {
                return false;
            }
            if (!$this->isNodeSimilar($value, $second->getNode($key))) {
                return false;
            }
        }

        // Ok, seems similar...
        return true;
    }

    private function getTransFunction(bool $plural): string
    {
        return $plural ? 'ngettext' : 'gettext';
    }
}
