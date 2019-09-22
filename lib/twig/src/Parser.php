<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 * (c) Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace WCML\Twig;

use WCML\Twig\Error\SyntaxError;
use WCML\Twig\Node\BlockNode;
use WCML\Twig\Node\BlockReferenceNode;
use WCML\Twig\Node\BodyNode;
use WCML\Twig\Node\Expression\AbstractExpression;
use WCML\Twig\Node\MacroNode;
use WCML\Twig\Node\ModuleNode;
use WCML\Twig\Node\Node;
use WCML\Twig\Node\NodeCaptureInterface;
use WCML\Twig\Node\NodeOutputInterface;
use WCML\Twig\Node\PrintNode;
use WCML\Twig\Node\TextNode;
use WCML\Twig\NodeVisitor\NodeVisitorInterface;
use WCML\Twig\TokenParser\TokenParserInterface;
/**
 * Default parser implementation.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Parser implements \WCML\Twig_ParserInterface
{
    protected $stack = [];
    protected $stream;
    protected $parent;
    protected $handlers;
    protected $visitors;
    protected $expressionParser;
    protected $blocks;
    protected $blockStack;
    protected $macros;
    protected $env;
    protected $reservedMacroNames;
    protected $importedSymbols;
    protected $traits;
    protected $embeddedTemplates = [];
    private $varNameSalt = 0;
    public function __construct(\WCML\Twig\Environment $env)
    {
        $this->env = $env;
    }
    /**
     * @deprecated since 1.27 (to be removed in 2.0)
     */
    public function getEnvironment()
    {
        @\trigger_error('The ' . __METHOD__ . ' method is deprecated since version 1.27 and will be removed in 2.0.', \E_USER_DEPRECATED);
        return $this->env;
    }
    public function getVarName()
    {
        return \sprintf('__internal_%s', \hash('sha256', __METHOD__ . $this->stream->getSourceContext()->getCode() . $this->varNameSalt++));
    }
    /**
     * @deprecated since 1.27 (to be removed in 2.0). Use $parser->getStream()->getSourceContext()->getPath() instead.
     */
    public function getFilename()
    {
        @\trigger_error(\sprintf('The "%s" method is deprecated since version 1.27 and will be removed in 2.0. Use $parser->getStream()->getSourceContext()->getPath() instead.', __METHOD__), \E_USER_DEPRECATED);
        return $this->stream->getSourceContext()->getName();
    }
    public function parse(\WCML\Twig\TokenStream $stream, $test = null, $dropNeedle = \false)
    {
        // push all variables into the stack to keep the current state of the parser
        // using get_object_vars() instead of foreach would lead to https://bugs.php.net/71336
        // This hack can be removed when min version if PHP 7.0
        $vars = [];
        foreach ($this as $k => $v) {
            $vars[$k] = $v;
        }
        unset($vars['stack'], $vars['env'], $vars['handlers'], $vars['visitors'], $vars['expressionParser'], $vars['reservedMacroNames']);
        $this->stack[] = $vars;
        // tag handlers
        if (null === $this->handlers) {
            $this->handlers = $this->env->getTokenParsers();
            $this->handlers->setParser($this);
        }
        // node visitors
        if (null === $this->visitors) {
            $this->visitors = $this->env->getNodeVisitors();
        }
        if (null === $this->expressionParser) {
            $this->expressionParser = new \WCML\Twig\ExpressionParser($this, $this->env);
        }
        $this->stream = $stream;
        $this->parent = null;
        $this->blocks = [];
        $this->macros = [];
        $this->traits = [];
        $this->blockStack = [];
        $this->importedSymbols = [[]];
        $this->embeddedTemplates = [];
        $this->varNameSalt = 0;
        try {
            $body = $this->subparse($test, $dropNeedle);
            if (null !== $this->parent && null === ($body = $this->filterBodyNodes($body))) {
                $body = new \WCML\Twig\Node\Node();
            }
        } catch (\WCML\Twig\Error\SyntaxError $e) {
            if (!$e->getSourceContext()) {
                $e->setSourceContext($this->stream->getSourceContext());
            }
            if (!$e->getTemplateLine()) {
                $e->setTemplateLine($this->stream->getCurrent()->getLine());
            }
            throw $e;
        }
        $node = new \WCML\Twig\Node\ModuleNode(new \WCML\Twig\Node\BodyNode([$body]), $this->parent, new \WCML\Twig\Node\Node($this->blocks), new \WCML\Twig\Node\Node($this->macros), new \WCML\Twig\Node\Node($this->traits), $this->embeddedTemplates, $stream->getSourceContext());
        $traverser = new \WCML\Twig\NodeTraverser($this->env, $this->visitors);
        $node = $traverser->traverse($node);
        // restore previous stack so previous parse() call can resume working
        foreach (\array_pop($this->stack) as $key => $val) {
            $this->{$key} = $val;
        }
        return $node;
    }
    public function subparse($test, $dropNeedle = \false)
    {
        $lineno = $this->getCurrentToken()->getLine();
        $rv = [];
        while (!$this->stream->isEOF()) {
            switch ($this->getCurrentToken()->getType()) {
                case \WCML\Twig\Token::TEXT_TYPE:
                    $token = $this->stream->next();
                    $rv[] = new \WCML\Twig\Node\TextNode($token->getValue(), $token->getLine());
                    break;
                case \WCML\Twig\Token::VAR_START_TYPE:
                    $token = $this->stream->next();
                    $expr = $this->expressionParser->parseExpression();
                    $this->stream->expect(\WCML\Twig\Token::VAR_END_TYPE);
                    $rv[] = new \WCML\Twig\Node\PrintNode($expr, $token->getLine());
                    break;
                case \WCML\Twig\Token::BLOCK_START_TYPE:
                    $this->stream->next();
                    $token = $this->getCurrentToken();
                    if (\WCML\Twig\Token::NAME_TYPE !== $token->getType()) {
                        throw new \WCML\Twig\Error\SyntaxError('A block must start with a tag name.', $token->getLine(), $this->stream->getSourceContext());
                    }
                    if (null !== $test && \call_user_func($test, $token)) {
                        if ($dropNeedle) {
                            $this->stream->next();
                        }
                        if (1 === \count($rv)) {
                            return $rv[0];
                        }
                        return new \WCML\Twig\Node\Node($rv, [], $lineno);
                    }
                    $subparser = $this->handlers->getTokenParser($token->getValue());
                    if (null === $subparser) {
                        if (null !== $test) {
                            $e = new \WCML\Twig\Error\SyntaxError(\sprintf('Unexpected "%s" tag', $token->getValue()), $token->getLine(), $this->stream->getSourceContext());
                            if (\is_array($test) && isset($test[0]) && $test[0] instanceof \WCML\Twig\TokenParser\TokenParserInterface) {
                                $e->appendMessage(\sprintf(' (expecting closing tag for the "%s" tag defined near line %s).', $test[0]->getTag(), $lineno));
                            }
                        } else {
                            $e = new \WCML\Twig\Error\SyntaxError(\sprintf('Unknown "%s" tag.', $token->getValue()), $token->getLine(), $this->stream->getSourceContext());
                            $e->addSuggestions($token->getValue(), \array_keys($this->env->getTags()));
                        }
                        throw $e;
                    }
                    $this->stream->next();
                    $node = $subparser->parse($token);
                    if (null !== $node) {
                        $rv[] = $node;
                    }
                    break;
                default:
                    throw new \WCML\Twig\Error\SyntaxError('Lexer or parser ended up in unsupported state.', $this->getCurrentToken()->getLine(), $this->stream->getSourceContext());
            }
        }
        if (1 === \count($rv)) {
            return $rv[0];
        }
        return new \WCML\Twig\Node\Node($rv, [], $lineno);
    }
    /**
     * @deprecated since 1.27 (to be removed in 2.0)
     */
    public function addHandler($name, $class)
    {
        @\trigger_error('The ' . __METHOD__ . ' method is deprecated since version 1.27 and will be removed in 2.0.', \E_USER_DEPRECATED);
        $this->handlers[$name] = $class;
    }
    /**
     * @deprecated since 1.27 (to be removed in 2.0)
     */
    public function addNodeVisitor(\WCML\Twig\NodeVisitor\NodeVisitorInterface $visitor)
    {
        @\trigger_error('The ' . __METHOD__ . ' method is deprecated since version 1.27 and will be removed in 2.0.', \E_USER_DEPRECATED);
        $this->visitors[] = $visitor;
    }
    public function getBlockStack()
    {
        return $this->blockStack;
    }
    public function peekBlockStack()
    {
        return isset($this->blockStack[\count($this->blockStack) - 1]) ? $this->blockStack[\count($this->blockStack) - 1] : null;
    }
    public function popBlockStack()
    {
        \array_pop($this->blockStack);
    }
    public function pushBlockStack($name)
    {
        $this->blockStack[] = $name;
    }
    public function hasBlock($name)
    {
        return isset($this->blocks[$name]);
    }
    public function getBlock($name)
    {
        return $this->blocks[$name];
    }
    public function setBlock($name, \WCML\Twig\Node\BlockNode $value)
    {
        $this->blocks[$name] = new \WCML\Twig\Node\BodyNode([$value], [], $value->getTemplateLine());
    }
    public function hasMacro($name)
    {
        return isset($this->macros[$name]);
    }
    public function setMacro($name, \WCML\Twig\Node\MacroNode $node)
    {
        if ($this->isReservedMacroName($name)) {
            throw new \WCML\Twig\Error\SyntaxError(\sprintf('"%s" cannot be used as a macro name as it is a reserved keyword.', $name), $node->getTemplateLine(), $this->stream->getSourceContext());
        }
        $this->macros[$name] = $node;
    }
    public function isReservedMacroName($name)
    {
        if (null === $this->reservedMacroNames) {
            $this->reservedMacroNames = [];
            $r = new \ReflectionClass($this->env->getBaseTemplateClass());
            foreach ($r->getMethods() as $method) {
                $methodName = \strtolower($method->getName());
                if ('get' === \substr($methodName, 0, 3) && isset($methodName[3])) {
                    $this->reservedMacroNames[] = \substr($methodName, 3);
                }
            }
        }
        return \in_array(\strtolower($name), $this->reservedMacroNames);
    }
    public function addTrait($trait)
    {
        $this->traits[] = $trait;
    }
    public function hasTraits()
    {
        return \count($this->traits) > 0;
    }
    public function embedTemplate(\WCML\Twig\Node\ModuleNode $template)
    {
        $template->setIndex(\mt_rand());
        $this->embeddedTemplates[] = $template;
    }
    public function addImportedSymbol($type, $alias, $name = null, \WCML\Twig\Node\Expression\AbstractExpression $node = null)
    {
        $this->importedSymbols[0][$type][$alias] = ['name' => $name, 'node' => $node];
    }
    public function getImportedSymbol($type, $alias)
    {
        if (null !== $this->peekBlockStack()) {
            foreach ($this->importedSymbols as $functions) {
                if (isset($functions[$type][$alias])) {
                    if (\count($this->blockStack) > 1) {
                        return null;
                    }
                    return $functions[$type][$alias];
                }
            }
        } else {
            return isset($this->importedSymbols[0][$type][$alias]) ? $this->importedSymbols[0][$type][$alias] : null;
        }
    }
    public function isMainScope()
    {
        return 1 === \count($this->importedSymbols);
    }
    public function pushLocalScope()
    {
        \array_unshift($this->importedSymbols, []);
    }
    public function popLocalScope()
    {
        \array_shift($this->importedSymbols);
    }
    /**
     * @return ExpressionParser
     */
    public function getExpressionParser()
    {
        return $this->expressionParser;
    }
    public function getParent()
    {
        return $this->parent;
    }
    public function setParent($parent)
    {
        $this->parent = $parent;
    }
    /**
     * @return TokenStream
     */
    public function getStream()
    {
        return $this->stream;
    }
    /**
     * @return Token
     */
    public function getCurrentToken()
    {
        return $this->stream->getCurrent();
    }
    protected function filterBodyNodes(\WCML\Twig_NodeInterface $node)
    {
        // check that the body does not contain non-empty output nodes
        if ($node instanceof \WCML\Twig\Node\TextNode && !\ctype_space($node->getAttribute('data')) || !$node instanceof \WCML\Twig\Node\TextNode && !$node instanceof \WCML\Twig\Node\BlockReferenceNode && $node instanceof \WCML\Twig\Node\NodeOutputInterface) {
            if (\false !== \strpos((string) $node, \chr(0xef) . \chr(0xbb) . \chr(0xbf))) {
                $t = \substr($node->getAttribute('data'), 3);
                if ('' === $t || \ctype_space($t)) {
                    // bypass empty nodes starting with a BOM
                    return;
                }
            }
            throw new \WCML\Twig\Error\SyntaxError('A template that extends another one cannot include content outside Twig blocks. Did you forget to put the content inside a {% block %} tag?', $node->getTemplateLine(), $this->stream->getSourceContext());
        }
        // bypass nodes that will "capture" the output
        if ($node instanceof \WCML\Twig\Node\NodeCaptureInterface) {
            return $node;
        }
        if ($node instanceof \WCML\Twig\Node\NodeOutputInterface) {
            return;
        }
        foreach ($node as $k => $n) {
            if (null !== $n && null === $this->filterBodyNodes($n)) {
                $node->removeNode($k);
            }
        }
        return $node;
    }
}
\class_alias('WCML\\Twig\\Parser', 'WCML\\Twig_Parser');
