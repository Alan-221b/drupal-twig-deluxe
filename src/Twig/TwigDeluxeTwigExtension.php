<?php

namespace Drupal\twig_deluxe\Twig;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Loader\ThemeRegistryLoader;
use Drupal\twig_deluxe\SimpleCSSParser;
use Drupal\twig_deluxe\Twig\NodeVisitor\ParentTreeMapVisitor;
use Drupal\twig_deluxe\Twig\NodeVisitor\ScopedVisitor;
use Twig\Extension\AbstractExtension;
use Twig\Template;
use Twig\TwigFunction;

/**
 * Provides a Twig extension for Twig Deluxe.
 */
final class TwigDeluxeTwigExtension extends AbstractExtension {

  use StringTranslationTrait;

  /**
   * Stores the hierarchy of the template inheritance based on extends tags.
   *
   * @var array
   */
  private array $parentTree = [];

  /**
   * The theme registry loader service.
   *
   * @var \Drupal\Core\Template\Loader\ThemeRegistryLoader
   */
  private ThemeRegistryLoader $loader;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * The theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  private ThemeHandlerInterface $themeHandler;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private FileSystemInterface $fileSystem;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private StateInterface $state;

  /**
   * Constructs a new TwigDeluxeTwigExtension object.
   */
  public function __construct(
    ThemeRegistryLoader $loader,
    ConfigFactoryInterface $configFactory,
    ThemeHandlerInterface $themeHandler,
    FileSystemInterface $fileSystem,
    StateInterface $state,
  ) {
    $this->loader = $loader;
    $this->configFactory = $configFactory;
    $this->themeHandler = $themeHandler;
    $this->fileSystem = $fileSystem;
    $this->state = $state;
  }

  /**
   * Gets the token parsers.
   *
   * @return \Drupal\twig_deluxe\Twig\TwigDeluxeScopedTokenParser[]
   *   An array of TwigDeluxeScopedTokenParser instances.
   */
  public function getTokenParsers(): array {
    return [new TwigDeluxeScopedTokenParser()];
  }

  /**
   * Gets the Twig functions.
   *
   * @return \Twig\TwigFunction[]
   *   An array of TwigFunction instances.
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('_scope', [
        $this,
        'scopeContents',
      ], ['is_safe' => ['html']]),
    ];
  }

  /**
   * Main entry point for scoped CSS & JS.
   *
   * This method is called by the TwigDeluxeScopedTokenParser when using
   * {% scoped %} tags.
   *
   * @param string $rawContents
   *   The raw contents to be scoped.
   * @param string $path
   *   Path to the twig template, used for hash generation.
   */
  public function scopeContents(string $rawContents, string $path): void {
    if (empty($rawContents)) {
      return;
    }

    $hash = $this->getScopedHash($path);

    // Extract CSS and JS Tags from the raw contents using DOMDocument.
    $dom = Html::load($rawContents);

    $css = '';
    $js = '';

    foreach ($dom->getElementsByTagName('style') as $style) {
      $css .= $style->nodeValue;
    }

    $css = trim($css);

    // Fix strange behaviour in drush call usage.
    $css = stripslashes($css);

    foreach ($dom->getElementsByTagName('script') as $script) {
      $js .= $script->nodeValue;
    }

    $js = trim($js);

    // Fix strange behaviour in drush call usage.
    $js = stripslashes($js);

    $this->scopeAndSaveCss($css, $hash);
    $this->scopeAndSaveJs($js, $hash);
  }

  /**
   * Generates a scoped hash based on the template path.
   *
   * @param string $templatePath
   *   The path to the template.
   *
   * @return string
   *   The generated hash.
   */
  protected function getScopedHash(string $templatePath): string {
    // Extract the absolute path to Drupal's root.
    $drupalRoot = DRUPAL_ROOT;

    // Remove the absolute path to Drupal's root from the template path.
    $templatePath = str_replace($drupalRoot, '', $templatePath);
    $templatePath = ltrim($templatePath, '/');

    // Generate a 8 character hash based on the template path.
    return substr(md5($templatePath), 0, 8);
  }

  /**
   * Gets all parent template hashes for a given template path.
   *
   * This method uses the stored parent tree map to find all ancestors
   * of the given template and their hashes.
   *
   * @param string $templatePath
   *   The path to the template.
   *
   * @return array
   *   An array of template hashes including the current template and all
   *   parents.
   */
  protected function getAllParentHashes(string $templatePath): array {
    $hashes = [];
    $currentHash = $this->getScopedHash($templatePath);

    // Add the current template's hash.
    $hashes[] = $currentHash;

    // Get the parent tree map from state.
    $parentTreeMap = $this->state->get('twig_deluxe.parent_tree', []);

    // Check if this template has any parent templates
    // The map is stored with parent hash as key and child hashes as values.
    foreach ($parentTreeMap as $parentHash => $childHashes) {
      if (in_array($currentHash, $childHashes)) {
        $hashes[] = $parentHash;

        // Recursively find parents of parents.
        $this->findParentHashes($parentHash, $parentTreeMap, $hashes);
      }
    }

    return array_unique($hashes);
  }

  /**
   * Recursively finds parent hashes.
   *
   * @param string $hash
   *   The hash to find parents for.
   * @param array $parentTreeMap
   *   The parent tree map.
   * @param array &$hashes
   *   The array of hashes to add to.
   */
  protected function findParentHashes(string $hash, array $parentTreeMap, array &$hashes): void {
    foreach ($parentTreeMap as $parentHash => $childHashes) {
      if (in_array($hash, $childHashes) && !in_array($parentHash, $hashes)) {
        $hashes[] = $parentHash;
        $this->findParentHashes($parentHash, $parentTreeMap, $hashes);
      }
    }
  }

  /**
   * Creates a CSS attribute selector for multiple hashes.
   *
   * This method generates a CSS selector that matches elements with any of the
   * provided hashes in their data-twig-scoped attribute.
   *
   * @param string $hash
   *   The primary hash to use for scoping.
   *
   * @return string
   *   The CSS attribute selector.
   */
  protected function createHashSelector(string $hash): string {
    // Create a selector that matches the exact hash or a hash that contains
    // this hash as part of a space-separated list.
    return "[data-twig-scoped~=\"$hash\"]";
  }

  /**
   * Scopes and saves CSS content.
   *
   * @param string $css
   *   The CSS content to scope and save.
   * @param string $hash
   *   The hash to use for scoping.
   */
  protected function scopeAndSaveCss(string $css, string $hash): void {
    // Parse CSS.
    $parser = new SimpleCSSParser($css);

    // Create a selector that matches elements with this hash.
    $hashSelector = $this->createHashSelector($hash);

    foreach ($parser->getAllRules() as $rule) {
      $rule->prependSelector("$hashSelector ");
    }

    // Save CSS.
    $css = $parser->render();

    // Save the CSS to a file, make sure the folder exists.
    $generatedCssPath = $this->getPathToChunksModule('css') . "/$hash.css";
    $newCss = trim($css);

    // Only write if the file has changed.
    if (file_exists($generatedCssPath)) {
      $existingCss = file_get_contents($generatedCssPath);
      if ($existingCss === $newCss) {
        return;
      }

      if (empty($newCss)) {
        unlink($generatedCssPath);
        return;
      }
    }

    if (empty($newCss)) {
      return;
    }

    file_put_contents($generatedCssPath, $newCss);
  }

  /**
   * Gets the path to the chunks module.
   *
   * @param string $subFolder
   *   The subfolder within the chunks module.
   * @param bool $ensureExists
   *   Whether to ensure the folder exists.
   *
   * @return string
   *   The path to the chunks module.
   */
  protected function getPathToChunksModule(string $subFolder = '', bool $ensureExists = TRUE): string {
    $theme = $this->configFactory->get('system.theme')->get('default');

    // Get the path to the theme.
    $themePath = $this->themeHandler->getTheme($theme)->getPath();

    $path = $themePath . '/twig-deluxe/chunks/' . $subFolder . '/';

    // Make sure that the folder exists. Use Drupal's FileSystem function to
    // ensure that the folder is created with the correct permissions.
    if ($ensureExists && !file_exists($path)) {
      $this->fileSystem->mkdir($path, 0777, TRUE);
    }

    return $path;
  }

  /**
   * Scopes and saves JavaScript content.
   *
   * @param string $js
   *   The JavaScript content to scope and save.
   * @param string $hash
   *   The hash to use for scoping.
   */
  protected function scopeAndSaveJs(string $js, string $hash): void {
    // Extract any import statements.
    $importStatements = [];
    $js = preg_replace_callback('/import\s+.*?(from)?\s?[\'"](@[^\'"]+\/)?[^\'"]+[\'"];?/s', function ($matches) use (&$importStatements) {
      $importStatements[] = $matches[0];
      return '';
    }, $js);

    // Wrap the JS code around Drupal behaviours.
    $wrappedJs = "
(function () {
// Preserve the variables so we can properly infer strings for Drupal.t 
// function.
const {Drupal, drupalSettings} = window;

Drupal.behaviors.twigDeluxe_$hash = {
  attach: function(context, settings) {
    $js
  },
  ready: false,
}

if (!Drupal.behaviors.twigDeluxe_$hash.ready) {
  Drupal.behaviors.twigDeluxe_$hash.attach(document, drupalSettings);
  Drupal.behaviors.twigDeluxe_$hash.ready = true;
}

if (import.meta.hot) {
  import.meta.hot.accept((newModule) => {
    if (newModule) {}
  })
}

})();";

    // Prepend any import statements.
    foreach ($importStatements as $importStatement) {
      $wrappedJs = $importStatement . "\n" . $wrappedJs;
    }

    // Save the JS a file, make sure the folder exists.
    $generatedJsPath = $this->getPathToChunksModule('js') . "/$hash.js";
    $newJs = trim($wrappedJs);

    // Only write if the file has changed.
    if (file_exists($generatedJsPath)) {
      if (empty($js)) {
        unlink($generatedJsPath);
        return;
      }

      $existingJS = file_get_contents($generatedJsPath);
      if ($existingJS === $newJs) {
        return;
      }
    }

    if (empty($js)) {
      return;
    }

    file_put_contents($generatedJsPath, $newJs);
  }

  /**
   * Scopes Twig output.
   *
   * This method is called when HTML generation is done on Twig templates
   * having a {% scope %} tag. It ensures that the CSS is scoped to the current
   * template and is compatible with block inheritance.
   *
   * @param string $html
   *   The HTML to scope.
   * @param \Twig\Template $template
   *   The Twig template.
   * @param string|null $hashName
   *   The hash name to use for scoping.
   *
   * @return string
   *   The scoped HTML.
   *
   * @throws \DOMException
   */
  public function scopeTwigOutput(string $html, Template $template, ?string $hashName = NULL): string {
    // Parse $html using DOMDocument.
    $dom = new \DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);
    $dom->encoding = 'UTF-8';

    $rootNodes = [];

    // Find the first wrapping element. If several are found, display a warning
    // using Drupal message and scope all root elements.
    /** @var \DOMNode $childNode */
    foreach ($dom->childNodes as $childNode) {
      if ($childNode->nodeType === XML_ELEMENT_NODE) {
        $rootNodes[] = $childNode;
      }
    }

    $templatePath = $template->getSourceContext()->getPath();

    if ($hashName) {
      // If a specific hash name is provided, use it.
      $hashValue = $hashName;
    }
    else {
      // Otherwise, get all parent hashes and combine them.
      $hashes = $this->getAllParentHashes($templatePath);
      $hashValue = implode(' ', $hashes);
    }

    foreach ($rootNodes as $wrappingElement) {
      if ($wrappingElement instanceof \DOMElement) {
        // Check if the attribute already exists.
        $existingAttr = $wrappingElement->getAttribute('data-twig-scoped');
        if (!empty($existingAttr)) {
          // Split the existing value into an array of hashes.
          $existingHashes = array_filter(explode(' ', $existingAttr));
          // Split the new hash value into an array.
          $newHashes = array_filter(explode(' ', $hashValue));
          // Merge both arrays and remove duplicates.
          $combinedHashes = array_unique(array_merge($existingHashes, $newHashes));
          // Join back into a string.
          $hashValue = implode(' ', $combinedHashes);
        }
        $wrappingElement->setAttribute('data-twig-scoped', $hashValue);
      }
    }

    $html = $dom->saveHTML();
    return preg_replace('/^<\?xml encoding="UTF-8" \?>\s*/', '', $html);
  }

  /**
   * Processes the parent tree.
   */
  public function processParentTree(): void {
    $hashMap = [];

    foreach ($this->parentTree as $child => $parent) {
      try {
        // Extract only the file name from the $parent path.
        $fileName = basename($parent);
        $this->loader->getCacheKey($fileName);
      }
      catch (\Exception $e) {
        // Skip if the parent template is not found.
        continue;
      }
      $parentHash = $this->getScopedHash($parent);
      // Add child hash into parent index.
      if (!isset($hashMap[$parentHash])) {
        $hashMap[$parentHash] = [];
      }

      $hashMap[$parentHash][] = $this->getScopedHash($child);
    }

    $currentMap = $this->state->get('twig_deluxe.parent_tree', []);

    // Merge the current map with the new map.
    $hashMap = array_merge($currentMap, $hashMap);

    // Save into Drupal state.
    $this->state->set('twig_deluxe.parent_tree', $hashMap);
  }

  /**
   * {@inheritdoc}
   */
  public function getNodeVisitors(): array {
    return [
      new ScopedVisitor(),
      new ParentTreeMapVisitor($this),
    ];
  }

  /**
   * Gets the parent tree.
   *
   * @return array
   *   The parent tree.
   */
  public function getParentTree(): array {
    return $this->parentTree;
  }

  /**
   * Sets the parent tree.
   *
   * @param string $child
   *   The child template.
   * @param string $parent
   *   The parent template.
   */
  public function setParentTree(string $child, string $parent): void {
    $this->parentTree[$child] = $parent;
  }

}
