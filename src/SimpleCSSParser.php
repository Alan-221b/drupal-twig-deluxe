<?php

namespace Drupal\twig_deluxe;

/**
 * SimpleCSSParser class.
 *
 * This class represents a simple CSS parser that extracts CSS rules from a
 * given string.
 */
class SimpleCSSParser {

  /**
   * The CSS content to parse.
   *
   * @var string
   */
  protected string $cssContent;

  /**
   * An array of CSS rule blocks.
   *
   * @var \Drupal\twig_deluxe\CSSRuleBlock[]
   */
  protected array $rules;

  /**
   * Constructs a SimpleCSSParser object.
   *
   * @param string $css
   *   The CSS content to parse.
   */
  public function __construct(string $css) {
    $this->cssContent = $css;
    $this->parse();
  }

  /**
   * Parses the CSS content and extracts rules.
   */
  private function parse(): void {
    // Special @screen tailwind directive.
    $tailwindScreen = '/(@media\s+screen\(\w+\))\s*\{((?:[^{}]+|\{(?:[^{}]+|\{[^{}]*})*})*)}/';
    preg_match_all($tailwindScreen, $this->cssContent, $screenMatches, PREG_SET_ORDER);

    // Remove all matched content from the css.
    $source = preg_replace($tailwindScreen, '', $this->cssContent);

    $pattern = '/([^{]+)\{([^}]+)}/';
    preg_match_all($pattern, $source, $matches, PREG_SET_ORDER);

    $rules = [];

    if (!empty($screenMatches)) {
      foreach ($screenMatches as $screenMatch) {
        $screenSize = $screenMatch[1];
        $screenContents = $screenMatch[2];
        preg_match_all($pattern, $screenContents, $screenMatches, PREG_SET_ORDER);
        $this->extractRules($screenMatches, $rules, $screenSize);
      }
    }

    $this->extractRules($matches, $rules);

    $this->rules = $rules;
  }

  /**
   * Extract CSS rules from matches and populate the rules array.
   *
   * @param array $matches
   *   An array of matches extracted from CSS content.
   * @param array $rules
   *   The array to store the extracted CSS rules.
   * @param string|null $screenSize
   *   The screen size for which the rules apply (optional).
   */
  private function extractRules(array $matches, array &$rules, ?string $screenSize = NULL): void {
    foreach ($matches as $match) {
      $selectors = explode(',', trim($match[1]));
      $rule = trim($match[2]);

      foreach ($selectors as $selector) {
        $rules[] = new CSSRuleBlock(trim($selector), $rule, $screenSize);
      }
    }
  }

  /**
   * Gets all CSS rules.
   *
   * @return \Drupal\twig_deluxe\CSSRuleBlock[]
   *   An array of CSS rule blocks.
   */
  public function getAllRules(): array {
    return $this->rules;
  }

  /**
   * Renders the CSS rules.
   *
   * @return string
   *   The rendered CSS content.
   */
  public function render(): string {
    $css = '';
    foreach ($this->rules as $rule) {
      $css .= $rule->render() . "\n";
    }
    return $css;
  }

}
