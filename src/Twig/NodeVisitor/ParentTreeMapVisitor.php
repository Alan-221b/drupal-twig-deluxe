<?php

namespace Drupal\twig_deluxe\Twig\NodeVisitor;

use Drupal\twig_deluxe\Twig\TwigDeluxeTwigExtension;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Implements a Twig Node Visitor to map parent-child relationships.
 *
 * This visitor traverses the Twig Abstract Syntax Tree (AST) and captures
 * information about template inheritance. It specifically looks for parent
 * templates and stores this information in a tree structure maintained by
 * the TwigDeluxeTwigExtension.
 *
 * @class ParentTreeMapVisitor
 */
class ParentTreeMapVisitor implements NodeVisitorInterface {

  /**
   * The Twig Deluxe extension for managing template relationships.
   *
   * @var \Drupal\twig_deluxe\Twig\TwigDeluxeTwigExtension
   */
  protected TwigDeluxeTwigExtension $twigDeluxeTwigExtension;

  public function __construct(TwigDeluxeTwigExtension $twigDeluxeTwigExtension) {
    $this->twigDeluxeTwigExtension = $twigDeluxeTwigExtension;
  }

  /**
   * {@inheritDoc}
   */
  public function enterNode(Node $node, Environment $env): Node {
    return $node;
  }

  /**
   * {@inheritDoc}
   */
  public function leaveNode(Node $node, Environment $env): ModuleNode|Node|null {
    if ($node instanceof ModuleNode === FALSE) {
      return $node;
    }

    if ($node->hasNode('parent')) {
      $parent = $node->getNode('parent');

      // Get the parent template.
      $parentTemplate = $parent->getAttribute('value');

      // If we are namespaced, resolve to the real path.
      if (str_starts_with($parentTemplate, '@')) {
        try {
          $parentTemplate = $env->resolveTemplate($parentTemplate)
            ->getSourceContext()
            ->getPath();
        }
        catch (LoaderError | SyntaxError $e) {
          return $node;
        }
      }

      // Save into the hash tree.
      $this->twigDeluxeTwigExtension->setParentTree($node->getTemplateName(), $parentTemplate);
    }

    return $node;
  }

  /**
   * {@inheritDoc}
   */
  public function getPriority(): int {
    return 255 + 1;
  }

}
