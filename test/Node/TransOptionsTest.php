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
use Twig\Extensions\Node\TransNodeOptions;
use Twig\Error\Error;

class TransOptionsTest extends TestCase
{
    /**
     *
     */
    public function testConstructorReturnsDefaultOptions()
    {
        $options = new TransNodeOptions();

        $this->assertSame(['%', '%'], $options->getDelimiters());
        $this->assertSame(false, $options->getNormalize());
        $this->assertSame(false, $options->getComplexVars());
    }

    /**
     *
     */
    public function testAcceptsDelimitersOption()
    {
        $options = new TransNodeOptions(["delimiters" => "{{}}"]);

        $this->assertSame(['{{', '}}'], $options->getDelimiters());
    }

    /**
     *
     */
    public function testAcceptsNormalizeOption()
    {
        $options = new TransNodeOptions(["normalize" => true]);

        $this->assertSame(true, $options->getNormalize());
    }

    /**
     *
     */
    public function testAcceptsComplexVarsOption()
    {
        $options = new TransNodeOptions(["complex_vars" => true]);

        $this->assertSame(true, $options->getComplexVars());
    }

    /**
     * @dataProvider delimiterTestProvider
     */
    public function testDelimiters($pattern, $expected, $exception = null)
    {
        if ($exception) $this->expectException($exception);

        $ops = new TransNodeOptions(['delimiters' => $pattern]);
        $this->assertSame($expected, $ops->getDelimiters());
    }

    public function delimiterTestProvider()
    {
        return array(
            ['{}',          ['{', '}'] ],
            ['[]',          ['[', ']'] ],
            ['[|]',         ['[', ']'] ],
            ['[[|]]',       ['[[', ']]'] ],
            ['{{|}}',       ['{{', '}}'] ],
            ['{{||||}}',    ['{{', '}}'] ],
            ['{{}}',        ['{{', '}}'] ],

            ['abcde',       null, Error::class],
            ['abcd',        ['ab', 'cd'] ],
            ['{{}',         null, Error::class],

            // Some edge cases
            ['{{|||}}||||', ['{{', '}}'] ],
            ['||||{{|||}}', ['{{', '}}'] ],
            ['||{{|||}}||', ['{{', '}}'] ],
            ['|  |{{ | | |}} | |', ['{{', '}}'] ],
        );
    }

}
