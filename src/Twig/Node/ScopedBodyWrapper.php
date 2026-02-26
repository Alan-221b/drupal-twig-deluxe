<?php

namespace Drupal\twig_deluxe\Twig\Node;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;

/**
 * Wraps the template body to capture yields for scoping.
 *
 * This node wraps the entire template body in a generator capture mechanism
 * that collects all yielded content, transforms it with scopeTwigOutput,
 * and yields the transformed result.
 */
#[YieldReady]
class ScopedBodyWrapper extends Node {

  /**
   * Constructs a ScopedBodyWrapper node.
   *
   * @param \Twig\Node\Node $body
   *   The original body node to wrap.
   * @param int $lineno
   *   The line number.
   */
  public function __construct(Node $body, int $lineno = 0) {
    parent::__construct(['body' => $body], [], $lineno);
  }

  /**
   * {@inheritdoc}
   */
  public function compile(Compiler $compiler): void {
    $compiler
      ->addDebugInfo($this)
      // Create a generator closure from the original body.
      // The 'yield from []' ensures this is always a generator even if body
      // is empty.
      ->write("\$__twig_deluxe_body_generator = (function () use (\$context, \$blocks, \$macros) {\n")
      ->indent()
      ->write("yield from [];\n")
      ->subcompile($this->getNode('body'))
      ->outdent()
      ->write("})();\n")
      // Collect all yields from the body generator.
      ->write("\$__twig_deluxe_collected = [];\n")
      ->write("foreach (\$__twig_deluxe_body_generator as \$__twig_deluxe_chunk) {\n")
      ->indent()
      ->write("\$__twig_deluxe_collected[] = \$__twig_deluxe_chunk;\n")
      ->outdent()
      ->write("}\n")
      // Transform and yield the complete output.
      ->write("\$__twig_deluxe_html = implode('', \$__twig_deluxe_collected);\n")
      ->write("yield \$this->env->getExtension('Drupal\\twig_deluxe\\Twig\\TwigDeluxeTwigExtension')->scopeTwigOutput(\$__twig_deluxe_html, \$this);\n");
  }

}
