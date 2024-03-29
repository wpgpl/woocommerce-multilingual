<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace WCML\Twig\Extension;

use WCML\Twig\Environment;
abstract class AbstractExtension implements \WCML\Twig\Extension\ExtensionInterface
{
    /**
     * @deprecated since 1.23 (to be removed in 2.0), implement \Twig_Extension_InitRuntimeInterface instead
     */
    public function initRuntime(\WCML\Twig\Environment $environment)
    {
    }
    public function getTokenParsers()
    {
        return [];
    }
    public function getNodeVisitors()
    {
        return [];
    }
    public function getFilters()
    {
        return [];
    }
    public function getTests()
    {
        return [];
    }
    public function getFunctions()
    {
        return [];
    }
    public function getOperators()
    {
        return [];
    }
    /**
     * @deprecated since 1.23 (to be removed in 2.0), implement \Twig_Extension_GlobalsInterface instead
     */
    public function getGlobals()
    {
        return [];
    }
    /**
     * @deprecated since 1.26 (to be removed in 2.0), not used anymore internally
     */
    public function getName()
    {
        return \get_class($this);
    }
}
\class_alias('WCML\\Twig\\Extension\\AbstractExtension', 'WCML\\Twig_Extension');
