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
use Twig\Extension\CoreExtension;
use Twig\Extensions\IntlExtension;

class_exists(CoreExtension::class);
class_exists(IntlExtension::class);

class IntlExtensionTest extends TestCase
{
    /**
     * @requires extension intl
     */
    public function testLocalizedDateFilterWithDateTimeZone()
    {
        $env = $this->getMockBuilder(Environment::class)->disableOriginalConstructor()->getMock();
        $date = \twig_localized_date_filter($env, new \DateTime('2015-01-01T00:00:00', new \DateTimeZone('UTC')), 'short', 'long', 'en', '+01:00');

        // twig_localized_date_filter outputs a "NARROW NO-BREAK SPACE U+202F"
        // as bytes 0xE2 0x80 0xAF between the time and " AM".
        // We're going to replace _any_ type of spaces to be able to compare.
        $date = preg_replace('/\s+/u', ' ', $date);

        $this->assertEquals('1/1/15, 12:00:00 AM GMT', $date);
    }

    /**
     * @requires extension intl
     */
    public function testLocalizedDateFilterWithDateTimeZoneZ()
    {
        $env = $this->getMockBuilder(Environment::class)->disableOriginalConstructor()->getMock();
        $date = \twig_localized_date_filter($env, new \DateTime('2017-11-19T00:00:00Z'), 'short', 'long', 'fr', 'Z');
        $this->assertEquals('19/11/2017 00:00:00 UTC', $date);
    }
}
