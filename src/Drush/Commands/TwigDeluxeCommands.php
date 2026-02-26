<?php

namespace Drupal\twig_deluxe\Drush\Commands;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Template\TwigEnvironment;
use Drush\Attributes\Command;
use Drush\Attributes\Usage;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * A Drush commandfile.
 */
final class TwigDeluxeCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs a TwigDeluxeCommands object.
   */
  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly TwigEnvironment $twig,
    private readonly ModuleExtensionList $extensionListModule,
    private readonly ThemeHandlerInterface $themeHandler,
  ) {
    parent::__construct();
  }

  /**
   * Compile all twig files with scoped behaviour.
   *
   * Ensure we also compile SDC twig files for proper scoped styles / script
   * extraction.
   *
   * @see vendor/drush/drush/src/Commands/core/TwigCommands.php
   */
  #[Command(name: 'twig_deluxe:compile', aliases: ['tdc'])]
  #[Usage(name: 'twig_deluxe:compile', description: 'Compile all twig templates, properly extract css/js in all scoped templates')]
  public function compile(): void {
    $searchPaths = [];

    // @phpstan-ignore-next-line
    require_once DRUSH_DRUPAL_CORE . "/themes/engines/twig/twig.engine";
    // Scan all enabled modules and themes.
    $modules = array_keys($this->moduleHandler->getModuleList());
    foreach ($modules as $module) {
      $searchPaths[] = $this->extensionListModule->getPath($module);
    }

    $themes = $this->themeHandler->listInfo();
    foreach ($themes as $theme) {
      $searchPaths[] = $theme->getPath();
    }

    $files = Finder::create()
      ->files()
      ->name(['*.html.twig'])
      ->exclude('tests')
      ->in($searchPaths);

    // Also we will add any component twig found in the current active front
    // theme.
    $activeTheme = $this->themeHandler->getTheme($this->themeHandler->getDefault());
    $activeThemePath = $activeTheme->getPath();
    $componentPath = $activeThemePath . '/components';

    if (is_dir($componentPath)) {
      $componentFiles = Finder::create()
        ->files()
        ->name(['*.twig'])
        ->in($componentPath);

      $files->append($componentFiles);
    }

    foreach ($files as $file) {
      $relative = Path::makeRelative($file->getRealPath(), Drush::bootstrapManager()
        ->getRoot());
      // Loading the template ensures the compiled template is cached.
      $this->twig->load($relative);
      $this->logger()
        ->success(dt('Compiled twig template !path', ['!path' => $relative]));
    }
  }

}
