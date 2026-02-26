<?php

namespace Drupal\twig_deluxe\Twig\Node;

use Twig\Compiler;
use Twig\Node\Node;
use Twig\Node\PrintNode;
use Twig\Node\TextNode;
use Twig\Attribute\YieldReady;

/**
 * Implements a scoped Twig node.
 */
#[YieldReady]
class Scoped extends Node {

  /**
   * Constructs a Scoped object.
   *
   * @param mixed $body
   *   The body of the node.
   * @param int $lineno
   *   The line number.
   * @param string|null $tag
   *   The tag name.
   */
  public function __construct($body, int $lineno = 0, ?string $tag = NULL) {
    parent::__construct([], ['raw' => $body], $lineno, $tag);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function compile(Compiler $compiler): void {
    $this->ensureNoTwigVariables($this->getAttribute('raw'));

    // Compile the scoped block using a generator to capture yields.
    // This is needed for Twig 3.9+ with use_yield=true.
    // The 'yield from []' ensures this is always a generator even if empty.
    $compiler
      ->addDebugInfo($this)
      ->write("\$__scoped_generator = (function () use (\$context, \$macros) {\n")
      ->indent()
      ->write("yield from [];\n")
      ->subcompile($this->getAttribute('raw'))
      ->outdent()
      ->write("})();\n")
      ->write("\$scopedContent = implode('', iterator_to_array(\$__scoped_generator));\n")
      ->write("if (!empty(trim(\$scopedContent))) {\n")
      ->indent()
      ->write("\$scopedContent = \$this->env->createTemplate(\$scopedContent)->render(\$context);\n")
      ->write("\$this->env->getExtension('Drupal\\twig_deluxe\\Twig\\TwigDeluxeTwigExtension')->scopeContents(\$scopedContent, \$this->getSourceContext()->getPath());\n")
      ->outdent()
      ->write("}\n");

    $this->externalCompilationHandler($compiler);

    parent::compile($compiler);
  }

  /**
   * Ensures that no Twig variables are present in the node.
   *
   * @param \Twig\Node\Node $node
   *   The node to check.
   *
   * @throws \Exception
   *   Thrown when a Twig variable is found.
   */
  protected function ensureNoTwigVariables(Node $node): void {
    // Loop on nodes, if we have a printNode in there, we have a variable.
    foreach ($node as $child) {
      if ($child instanceof TextNode) {
        continue;
      }
      // @phpstan-ignore-next-line
      if ($child instanceof Node) {
        $this->ensureNoTwigVariables($child);
      }

      if ($child instanceof PrintNode) {
        throw new \Exception(sprintf('Scoped CSS/JS cannot contain Twig variables in %s on line %d. Consider using a data attribute in the DOM instead.',
          $this->getTemplateName(),
          $child->getTemplateLine()
        ));
      }
    }
  }

  /**
   * Handles external compilation of scoped content.
   *
   * This method is responsible for compiling scoped content when the code
   * is executed in a CLI environment. It retrieves the raw content,
   * and uses the TwigDeluxeTwigExtension to scope the contents.
   *
   * @param \Twig\Compiler $compiler
   *   The Twig compiler instance.
   */
  protected function externalCompilationHandler(Compiler $compiler): void {
    if (php_sapi_name() !== 'cli') {
      return;
    }

    $contents = $this->getAttribute('raw');
    $data = $contents;

    // Try to use the data attribute, if not found fallback on whole $contents
    // as string.
    if ($contents instanceof TextNode) {
      $data = $contents->hasAttribute('data') ?
        $contents->getAttribute('data') : $contents;
    }

    $compiler->getEnvironment()
      ->getExtension('Drupal\twig_deluxe\Twig\TwigDeluxeTwigExtension')
      ->scopeContents($data, $this->getSourceContext()
        ->getPath());
  }

}
