<?php

namespace Drupal\twig_deluxe\Twig\NodeVisitor;

use Drupal\twig_deluxe\Twig\Node\Scoped;
use Drupal\twig_deluxe\Twig\Node\ScopedBodyWrapper;
use Twig\Environment;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Flags scoped templates and wraps the body for CSS scoping.
 *
 * This visitor detects templates that contain {% scoped %} tags and wraps
 * their body in a ScopedBodyWrapper node that captures all yielded output,
 * transforms it to add data-twig-scoped attributes, and yields the result.
 */
class ScopedVisitor implements NodeVisitorInterface {

  /**
   * An array to keep track of templates that need scoping.
   *
   * @var array
   */
  private array $needsScoping = [];

  /**
   * {@inheritdoc}
   */
  public function getPriority(): int {
    // Should be run before the OptimizerExtension.
    return 255 + 1;
  }

  /**
   * {@inheritdoc}
   */
  public function enterNode(Node $node, Environment $env): Node {
    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function leaveNode(Node $node, Environment $env): ?Node {
    // Flag the template as needing scoping.
    if ($node instanceof Scoped) {
      $this->needsScoping[$node->getTemplateName()] = TRUE;
    }

    // Wrap the body node to capture and transform all yields.
    if ($node instanceof ModuleNode && array_key_exists($node->getTemplateName(), $this->needsScoping)) {
      $body = $node->getNode('body');
      $wrappedBody = new ScopedBodyWrapper($body, $body->getTemplateLine());
      $node->setNode('body', $wrappedBody);
    }

    return $node;
  }

}
