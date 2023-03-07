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

use Twig\TwigFilter;
use Twig\Extension\AbstractExtension;
use Twig\Extensions\AsFilterExpression;
use Twig\Extensions\Node\TransNodeOptions;
use Twig\Extensions\TokenParser\TransTokenParser;
    
class I18nExtension extends AbstractExtension
{
    /**
     * Holds the current options for I18n extension.
     *
     * @see Twig\Extensions\Node\TransNodeOptions
     */
    private $options;

    /**
     * Constructor for I18n Extension.
     *
     * @param array $options Set of options for this Extension instance.
     * @see Twig_Extensions_Node_Trans_Options
     */
    public function __construct(array $options = [])
    {
        $this->options = new TransNodeOptions($options);
    }

    public function getTokenParsers()
    {
        return [new TransTokenParser($this->options)];
    }

    public function getFilters()
    {
        return [
             new TwigFilter('trans', 'gettext'),
             new TwigFilter('as', null, ['node_class' => AsFilterExpression::class])
        ];
    }
}
