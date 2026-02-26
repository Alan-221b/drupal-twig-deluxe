<?php

namespace Drupal\twig_deluxe;

/**
 * Represents a CSS rule block.
 *
 * @property string $selector
 *   The CSS selector for this rule block.
 * @property string $rule
 *   The CSS rule content.
 */
class CSSRuleBlock {

  /**
   * The screen size for media query, if applicable.
   *
   * @var string|null
   */
  protected ?string $screen;

  /**
   * The CSS selector.
   *
   * @var string
   */
  protected string $selector;

  /**
   * The CSS rule content.
   *
   * @var string
   */
  protected string $rule;

  /**
   * Constructs a CSSRuleBlock object.
   *
   * @param string $selector
   *   The CSS selector.
   * @param string $rule
   *   The CSS rule content.
   * @param string|null $screen
   *   The screen size for media query, if applicable.
   */
  public function __construct(string $selector, string $rule, ?string $screen = NULL) {
    $this->selector = $selector;
    $this->rule = $rule;
    $this->screen = $screen;
  }

  /**
   * Gets the CSS selector.
   *
   * @return string
   *   The CSS selector.
   */
  public function getSelector(): string {
    return $this->selector;
  }

  /**
   * Gets the CSS rule content.
   *
   * @return string
   *   The CSS rule content.
   */
  public function getRule(): string {
    return $this->rule;
  }

  /**
   * Sets the CSS selector.
   *
   * @param string $selector
   *   The CSS selector to set.
   */
  public function setSelector(string $selector): void {
    $this->selector = $selector;
  }

  /**
   * Prepends a selector to the existing selector.
   *
   * @param string $selector
   *   The selector to prepend.
   */
  public function prependSelector(string $selector): void {
    // If we have a root selector, we return a concatenation of the new
    // selector.
    if (str_starts_with($this->selector, 'root')) {
      $this->selector = rtrim($selector) . substr($this->selector, 4);
      return;
    }
    $this->selector = $selector . $this->selector;
  }

  /**
   * Sets the CSS rule content.
   *
   * @param string $rule
   *   The CSS rule content to set.
   */
  public function setRule(string $rule): void {
    $this->rule = $rule;
  }

  /**
   * Renders the CSS rule block.
   *
   * @return string
   *   The rendered CSS rule block.
   */
  public function render(): string {
    $this->indent($this->screen ? 4 : 2);
    $render = $this->selector . " {\n" . $this->rule . "\n}";
    // If we have a screen size, we need to wrap the rule in a media query.
    if ($this->screen) {
      $render = "{$this->screen} {\n" . $render . "\n}";
    }
    return $render;
  }

  /**
   * Indents the CSS rule content.
   *
   * @param int $spaces
   *   The number of spaces to indent.
   */
  protected function indent(int $spaces = 2): void {
    $lines = explode("\n", $this->rule);
    $indented = '';
    foreach ($lines as $line) {
      $indented .= str_repeat(' ', $spaces) . trim($line) . "\n";
    }
    $this->rule = rtrim($indented);
  }

}
