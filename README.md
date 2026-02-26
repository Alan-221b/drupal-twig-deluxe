# Twig Deluxe

Scoped CSS and JavaScript for Drupal Twig templates, inspired by Vue and Svelte single-file components.

## Overview

Twig Deluxe brings component-scoped styles and scripts to Drupal theming. Write `<style>` and `<script>` tags inside `{% scoped %}...{% endscoped %}` blocks directly in your `.html.twig` files. At build time, the module extracts these into individual chunk files and automatically scopes CSS rules to the template using `data-twig-scoped` attributes on root HTML elements.

The result: styles defined in a template only affect that template's markup, with no global leakage. JavaScript is wrapped in Drupal behaviors and loaded on demand, only for pages that render the template.

This is a compile-time tool. The `drush twig_deluxe:compile` command processes your templates and writes chunk files to your theme directory. Your theme's asset pipeline (Vite or similar) then picks them up during the normal build.

## Requirements

- Drupal 10 or 11
- PHP 8.1+
- A theme using a modern bundler (Vite recommended) capable of glob imports and dynamic `import()` for the JS chunk pipeline

## Installation

```bash
composer require drupal/twig_deluxe
drush en twig_deluxe
```

## Usage

Place a `{% scoped %}` block anywhere in a `.html.twig` file. The block can contain a `<style>` tag, a `<script>` tag, or both.

### CSS only

```twig
<div class="my-component">
  <h2>{{ title }}</h2>
  {{ content }}
</div>

{% scoped %}
  <style>
    h2 { color: navy; font-size: 1.5rem; }
    .my-component { padding: 2rem; }
  </style>
{% endscoped %}
```

At compile time, the `h2` and `.my-component` rules are rewritten to include the template's scope hash, and the `<div class="my-component">` root element receives a `data-twig-scoped="HASH"` attribute in the rendered HTML.

### CSS and JavaScript

```twig
<div class="carousel" x-data="carousel">
  {{ items }}
</div>

{% scoped %}
  <style>
    .carousel { position: relative; overflow: hidden; }
  </style>
  <script>
    import Splide from '@splidejs/splide';
    document.addEventListener('alpine:init', () => {
      Alpine.data('carousel', () => ({
        init() { new Splide(this.$el).mount(); }
      }));
    });
  </script>
{% endscoped %}
```

### Twig variables in scoped blocks

Twig variables **cannot** be used inside `{% scoped %}` blocks. This is enforced at compile time. The contents of a scoped block are static — they are extracted before Twig renders the template.

If you need to pass dynamic values to your scoped script, use `data-*` attributes on the DOM element:

```twig
<div class="my-component" data-color="{{ color }}">
  {{ content }}
</div>

{% scoped %}
  <script>
    Drupal.behaviors.myComponent = {
      attach(context) {
        const el = context.querySelector('.my-component');
        if (!el) return;
        const color = el.dataset.color;
        // use color...
      }
    };
  </script>
{% endscoped %}
```

## Theme Integration

After running `drush twig_deluxe:compile`, the module writes chunk files into your active theme. The theme must be set up to consume them.

The module auto-creates these directories in your theme:

```
{theme}/twig-deluxe/chunks/css/
{theme}/twig-deluxe/chunks/js/
```

### CSS

Create a file at `{theme}/twig-deluxe/generated.css` with the following content:

```css
@import-glob "./chunks/css/*.css";
```

Then import it from your theme's main stylesheet:

```css
/* main.css */
@import "../twig-deluxe/generated.css";
```

The `@import-glob` syntax requires a PostCSS plugin such as `postcss-import` combined with `postcss-import-ext-glob`, or Vite's glob import support via a plugin.

### JavaScript

In your theme's entry point (`main.ts` or `main.js`), add the following to dynamically load JS chunks for any scoped elements present on the page:

```js
const chunks = import.meta.glob('../twig-deluxe/chunks/js/**/*.js');

document.querySelectorAll('[data-twig-scoped]').forEach(async (el) => {
  const hashes = el.getAttribute('data-twig-scoped').split(' ');
  for (const hash of hashes) {
    const path = `../twig-deluxe/chunks/js/${hash}.js`;
    if (chunks[path]) await chunks[path]();
  }
});
```

This pattern uses Vite's `import.meta.glob` to statically analyze all chunk files at build time, then loads only the ones needed for the current page at runtime.

## Drush Commands

### `drush twig_deluxe:compile`

Alias: `tdc`

Scans all enabled modules and the active theme for `.html.twig` files, extracts `{% scoped %}` blocks, and writes CSS and JS chunk files to the active theme's `twig-deluxe/chunks/` directory.

```bash
drush twig_deluxe:compile
```

Run this command before your theme's asset build step in CI/CD pipelines. The typical order is:

1. `drush twig_deluxe:compile`
2. `npm run build` (or equivalent)

Chunk files are named by hash, so unchanged templates produce identical filenames and content. This makes the output safe to commit to version control.

## How It Works

Each template gets a scope hash derived from its file path (MD5, first 8 characters). This hash is stable across runs as long as the file path doesn't change.

At compile time:

1. The `{% scoped %}` block is parsed out of the template source.
2. CSS rules are rewritten by prepending `[data-twig-scoped~="HASH"]` to every selector. For example, `h2 { color: navy; }` becomes `[data-twig-scoped~="a3f9c1b2"] h2 { color: navy; }`.
3. The rewritten CSS is written to `chunks/css/HASH.css`.
4. JavaScript is written as-is to `chunks/js/HASH.js`.
5. The template's root HTML element (the outermost tag) is modified to include `data-twig-scoped="HASH"` as a Twig attribute.

The `root` pseudo-selector maps to the scoped element itself. `root:hover` becomes `[data-twig-scoped~="HASH"]:hover`, targeting the root element directly rather than a descendant.

## Template Inheritance

When a child template extends a parent using `{% extends %}`, the child inherits the parent's scope hash. CSS defined in the parent's `{% scoped %}` block applies correctly to markup rendered by the child, because the `data-twig-scoped` attribute on the root element carries the parent's hash.

If both parent and child define `{% scoped %}` blocks, the root element will carry both hashes as a space-separated list: `data-twig-scoped="PARENT_HASH CHILD_HASH"`. Both stylesheets apply.

## Limitations

- **No Twig variables in scoped blocks.** The contents of `{% scoped %}` are extracted statically, before Twig renders the template. Any `{{ variable }}` inside a scoped block will cause a compile error.
- **CSS is parsed by regex.** Complex or malformed CSS may not scope correctly. Standard rule sets work reliably; at-rules like `@keyframes` and `@media` are handled, but deeply nested or non-standard syntax may produce unexpected output.
- **Requires a bundler.** The chunk import pipeline depends on `import.meta.glob` or an equivalent mechanism. Without a bundler that supports this, JS chunks won't load.
- **The `root` selector prefix** maps to the scoped element itself. `root .child` becomes `[data-twig-scoped~="HASH"] .child`. `root:hover` becomes `[data-twig-scoped~="HASH"]:hover`.
- **One `{% scoped %}` block per template.** Multiple scoped blocks in a single file are not supported.

## License

GPL-2.0-or-later. See [LICENSE](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).
