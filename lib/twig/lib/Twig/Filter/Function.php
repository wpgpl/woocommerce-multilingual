<?php

namespace WCML;

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
@\trigger_error('The Twig_Filter_Function class is deprecated since version 1.12 and will be removed in 2.0. Use \\Twig\\TwigFilter instead.', \E_USER_DEPRECATED);
/**
 * Represents a function template filter.
 *
 * Use \Twig\TwigFilter instead.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since 1.12 (to be removed in 2.0)
 */
class Twig_Filter_Function extends \WCML\Twig_Filter
{
    protected $function;
    public function __construct($function, array $options = [])
    {
        $options['callable'] = $function;
        parent::__construct($options);
        $this->function = $function;
    }
    public function compile()
    {
        return $this->function;
    }
}
