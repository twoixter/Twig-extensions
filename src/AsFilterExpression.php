<?php

/*
 * This file is part of Twig.
 *
 * (c) 2010-2019 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Extensions;

use Twig\Compiler;
use Twig\Node\Node;
use Twig\Error\SyntaxError;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\ConstantExpression;

class AsFilterExpression extends FilterExpression
{
    public function __construct(Node $node, ConstantExpression $filterName, Node $arguments, int $lineno, string $tag = null)
    {
        if (($arguments->count() != 1) || (!$arguments->getNode(0) instanceof ConstantExpression)) {
            throw new SyntaxError('Invalid parameters to "as" filter.', $lineno);
        }

        parent::__construct($node, $filterName, $arguments, $lineno, $tag);
    }

    /**
     * Compile an "As" filter for I18n variable alias.
     * This is a dummy filter, so it just need to pass compilation to its node.
     */
    public function compile(Compiler $compiler): void
    {
        $this->getNode('node')->compile($compiler);
    }
}
