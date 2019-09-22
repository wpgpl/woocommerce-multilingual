<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace WCML\Twig\Loader;

use WCML\Twig\Error\LoaderError;
use WCML\Twig\Source;
/**
 * Loads a template from an array.
 *
 * When using this loader with a cache mechanism, you should know that a new cache
 * key is generated each time a template content "changes" (the cache key being the
 * source code of the template). If you don't want to see your cache grows out of
 * control, you need to take care of clearing the old cache file by yourself.
 *
 * This loader should only be used for unit testing.
 *
 * @final
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ArrayLoader implements \WCML\Twig\Loader\LoaderInterface, \WCML\Twig\Loader\ExistsLoaderInterface, \WCML\Twig\Loader\SourceContextLoaderInterface
{
    protected $templates = [];
    /**
     * @param array $templates An array of templates (keys are the names, and values are the source code)
     */
    public function __construct(array $templates = [])
    {
        $this->templates = $templates;
    }
    /**
     * Adds or overrides a template.
     *
     * @param string $name     The template name
     * @param string $template The template source
     */
    public function setTemplate($name, $template)
    {
        $this->templates[(string) $name] = $template;
    }
    public function getSource($name)
    {
        @\trigger_error(\sprintf('Calling "getSource" on "%s" is deprecated since 1.27. Use getSourceContext() instead.', \get_class($this)), \E_USER_DEPRECATED);
        $name = (string) $name;
        if (!isset($this->templates[$name])) {
            throw new \WCML\Twig\Error\LoaderError(\sprintf('Template "%s" is not defined.', $name));
        }
        return $this->templates[$name];
    }
    public function getSourceContext($name)
    {
        $name = (string) $name;
        if (!isset($this->templates[$name])) {
            throw new \WCML\Twig\Error\LoaderError(\sprintf('Template "%s" is not defined.', $name));
        }
        return new \WCML\Twig\Source($this->templates[$name], $name);
    }
    public function exists($name)
    {
        return isset($this->templates[(string) $name]);
    }
    public function getCacheKey($name)
    {
        $name = (string) $name;
        if (!isset($this->templates[$name])) {
            throw new \WCML\Twig\Error\LoaderError(\sprintf('Template "%s" is not defined.', $name));
        }
        return $name . ':' . $this->templates[$name];
    }
    public function isFresh($name, $time)
    {
        $name = (string) $name;
        if (!isset($this->templates[$name])) {
            throw new \WCML\Twig\Error\LoaderError(\sprintf('Template "%s" is not defined.', $name));
        }
        return \true;
    }
}
\class_alias('WCML\\Twig\\Loader\\ArrayLoader', 'WCML\\Twig_Loader_Array');
