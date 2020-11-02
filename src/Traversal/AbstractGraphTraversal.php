<?php

declare(strict_types=1);

namespace RND\GremlinDSL\Traversal;

use Closure;
use RND\GremlinDSL\Configuration;
use RND\GremlinDSL\Exception\NoSendClosureException;
use RND\GremlinDSL\Traversal\Steps\AnonymousStep;
use RND\GremlinDSL\Traversal\Steps\AssignStep;
use RND\GremlinDSL\Traversal\Steps\GStep;
use RND\GremlinDSL\Traversal\Steps\NextStep;
use RND\GremlinDSL\Traversal\Steps\RawStep;
use RND\GremlinDSL\Traversal\Steps\TraversalStepInterface;

class AbstractGraphTraversal implements GraphTraversalInterface
{

    protected Steps $steps;

    public function __construct(?Steps $steps = null)
    {
        $this->steps = $steps ?? Steps::create();
    }

    public function __toString()
    {
        $steps = '';
        $previousStep = null;
        foreach ($this->steps as $index => $step) {
            if ($step instanceof TraversalStepInterface
                && $index !== 0
                && !$previousStep instanceof RawStep
                && !$previousStep instanceof AssignStep
            ) {
                $steps .= '.';
            }
            $previousStep = $step;
            $steps .= $step->__toString();
        }

        return $steps;
    }

    public static function g(): self
    {
        $instance = new static();
        $instance->steps->add(new GStep());

        return $instance;
    }

    public static function __(): self
    {
        $instance = new static();
        $instance->steps->add(new AnonymousStep());

        return $instance;
    }

    public function raw(string $raw): self
    {
        $this->steps->prepend(new RawStep($raw));

        return $this;
    }

    public function assign(string $assignment): self
    {
        $this->steps->prepend(new AssignStep($assignment));

        return $this;
    }

    public function next(...$args): self
    {
        $this->steps->add(new NextStep(...$args));

        return $this;
    }

    /**
     * Example usage with provided closure:
     * ```php
     *   g()->send(function (string $traversalString) {});
     * ```
     *
     * Example usage with configured closure:
     * ```php
     *   Configuration::getInstance()->setSendClosure(function (string $traversalString) {})
     *   g()->send();
     * ```
     *
     * @param SendClosureInterface|Closure|null $closure The function handling the send step.
     *                                                   If not provided the configured `Configuration::setSendClosure()` one will be used.
     *                                                   The context of the closure will be set to the current GraphTraversal.
     *                                                   The first parameter is the compiled traversal string.
     *                                                   Example closure:
     *                                                   <code>
     * function (string $traversalString) use ($connection) { return $connection->send($traversalString); }
     *                                                   </code>
     * @return mixed the result
     * @see Configuration::setSendClosure()
     */
    public function send($closure = null)
    {
        $closure = $closure ?? Configuration::getInstance()->getSendClosure();
        if (!$closure) {
            throw new NoSendClosureException();
        }

        return $closure instanceof SendClosureInterface
            ? $closure($this, $this->__toString())
            : $closure->call($this, $this->__toString());
    }
}
