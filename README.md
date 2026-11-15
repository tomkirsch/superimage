# SuperImage - Responsive Images for CodeIgniter 4

A modern responsive image library for CodeIgniter 4 with on-the-fly resizing, versioned cache filenames, and modern HTML output (`<picture>` / `<img srcset>`). By default (`cacheBustingMethod = 'mtime'`), stale requests are redirected (302) to the current versioned URL.

## Requirements and formats

Requires PHP `^8.0`, CodeIgniter `^4`, and GD with the encoder for the selected output format.
The suite is tested on PHP 8.3.6 / CI4 4.7.0; the CI4 `convert()` API was also checked in 4.4.6–4.7.6.
Older CI4 versions and PHP 8.0 are not runtime-tested here; the dependency floors are unchanged.
Output formats are **WebP, JPEG (`jpg` or `jpeg`), and PNG**. Other output extensions,
including AVIF, return 404; AVIF output was never supported by CI4's GD handler.

## Upgrading to 0.5

- Use `^0.5` in consuming projects (this is not a 0.4.x patch).
- **Clear existing generated files once with `Services::resizer()->cleanAll()`**:
  older `.webp` cache files can contain JPEG bytes. Only run this against a dedicated cache directory;
  `cleanAll()` removes every file there, including `.htaccess` or unrelated files.
- Separately remove any legacy `*.php`, `*.phtml`, and `*.html` files from that cache directory
  if they remain (for example, in a different old cache root).
- Replace the Apache rule below so only `(webp|jpe?g|png)` variants use its fast path.
- Off-ladder widths and stale versions now redirect with **302**, not 301. Widths snap up to the
  configured `widthLadder`, capped by source width and `maxSize` (or `maxSize` when upscaling).
  The cap is also an allowed width: at most `count(widthLadder) + 1` widths per image per format.
- The only new config options are `widthLadder` and `maxSourcePixels` (default 40 million).
  Outputs above 25 million pixels or 16383 pixels on either axis are rejected with 404.
- Builder and fixed-list markup changes intentionally: builders use fluid `vw` sizes and `w`
  descriptors; fixed lists (including `[150]`) use an `<img>` with a fixed `sizes` slot equal to
  the smallest supplied width. Presets retain `<picture>` and gain a high-DPR phone fallback.
- `resolutionStep` must be finite and positive; `maxResolution` finite and at least 1. Configurations
  requiring more than 1000 resolution increments are rejected instead of looping indefinitely.

Keep `cachePath` **dedicated to generated images**, outside source assets, and not writable by
untrusted users. No symlink/TOCTOU protection is provided for locally writable cache trees.
The cleanup helpers are unchanged: create the cache directory before calling `cleanAll()` or
`cleanExpired()`, and do not point them at a directory containing anything you need to keep.

Use numeric `appVersion` / `cacheVersion` values: the public URL grammar accepts digits only.
A per-render `cacheVersion` override still redirects to the server's configured version. Prefer
`mtime` (the default); `time` changes every second and is unsuitable for stable caching.
EXIF auto-orientation is not added; pre-orient phone JPEGs before using them as sources.

## Install

```
composer require tomkirsch/superimage
```

### Create `app\config\SuperImage.php`

These are some basic values. See vendor/tomkirsch/superimage/src/SuperImageConfig.php for all settings.

```php
<?php

namespace Config;

class SuperImage extends \Tomkirsch\SuperImage\SuperImageConfig
{
    public string $sourcePath = WRITEPATH . 'img/'; // where your hires assets live
    public string $cachePath = FCPATH . '_superimage_cache/'; // dedicated generated-image directory; public only for direct Apache caching
    public string $servingStrategy = 'htaccess'; // most efficient. falls back to php's readfile()
    public string $publicUrlPrefix = 'img/';
    public string $defaultOutputExt = 'webp';
    public bool $enableCacheBusting = true;
    public string $cacheBustingMethod = 'mtime'; // use mtime so updated images get automatically stale
}
```

### .htaccess serving (default)

True htaccess serving requires cached files to be in public. Add to .gitignore:

```
public/_superimage_cache/
```

Paste this after RewriteEngine ON:

```
    # ============================================================================
    # SUPERIMAGE SERVING
    # ============================================================================
    # These rules MUST come BEFORE CodeIgniter's main rewrite rules
    # Match pattern: img/{anything}-w{number}-v{version}.{ext}
    # Examples:
    #   /img/image_1.jpg-w1200-v1737063600.webp
    #   /img/products/photo.jpg-w800-v1737063600.jpg
    #   /img/blog/hero.jpg-w1920-v1737063600.png

    # Check if the cached version of the requested image exists
    # We use a look-ahead to capture the groups from the rule below
    # NOTE: this will NOT work when serving via spark!
    RewriteCond %{DOCUMENT_ROOT}/_superimage_cache/$1-w$2-v$3.$4 -f

    # If it exists, serve it and stop (L)!
    # (comment out the CI redirect to test true apache cache serving)
    RewriteRule ^img/(.+)-w(\d+)-v(\d+)\.(webp|jpe?g|png)$ _superimage_cache/$1-w$2-v$3.$4 [L]

    # ============================================================================
    # CACHE HEADERS FOR IMAGES (Optional but Recommended)
    # ============================================================================
    # Sets proper caching headers for image files
    # This works for both cached resizes AND original images
    # ============================================================================

    <IfModule mod_headers.c>
        # Match image files
        <FilesMatch "\.(webp|jpe?g|png)$">
            # Cache for 1 year (versions are in filenames)
            Header set Cache-Control "public, max-age=31536000, immutable"

            # Security: Prevent hotlinking (optional - uncomment if needed)
            # RewriteCond %{HTTP_REFERER} !^$
            # RewriteCond %{HTTP_REFERER} !^https?://(www\.)?yourdomain\.com [NC]
            # RewriteRule \.(webp|jpg|jpeg|png|gif)$ - [F,L]
        </FilesMatch>
    </IfModule>
```

#### NOTE!

You might need to change the path to the DOCUMENT_ROOT above to match your setup.

## config/Services.php

```php
    public static function superImage(?array $options = null, ?SuperImageConfig $config = null): \Tomkirsch\SuperImage\SuperImage
    {
        $config = $config ?? new \Config\SuperImage();
        $instance = new \Tomkirsch\SuperImage\SuperImage($config);
        if (!empty($options)) {
            $instance->load($options);
        }
        return $instance;
    }

    public static function resizer(?SuperImageConfig $config = null): \Tomkirsch\SuperImage\Resizer
    {
        $config = $config ?? new \Config\SuperImage();
        return new \Tomkirsch\SuperImage\Resizer($config);
    }
```

## config/Routes.php

```php
<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->get('img/(.+)', 'Home::serveImage/$1');
// optional - cache management
```

## controllers/Home.php

```php
    /**
     * Serve image request
     */
    public function serveImage(...$path)
    {
        $path = implode("/", $path);
        \Config\Services::resizer()->serve($path);
    }
```

## View

The `src` parameter must be a relative base filename with extension:

```php
'src' => 'hero.jpg'               // relative base name with extension
'src' => 'product_1.jpg'          // relative base name with extension
'src' => 'products/photo_1.png'   // relative base name with subdirectory and extension
```

The source path is resolved from your config's `$sourcePath` setting.

### Supported `render()` options (current `SuperImage` class)

`src` is an alias for `file`.

- `src` / `file`
- `outputExt` (`webp`, `jpg`, `jpeg`, `png`)
- `widths` (`'full'`, `'half'`, `'third'`, `'quarter'`, `'two-thirds'`, builder, array, or decimal fraction)
- `gutter`, `static`, `maxResolution`, `resolutionStep`
- `maxWidth`, `maxHeight` (supports `'source'`)
- `loading` (`'lazy' | 'eager' | 'auto'`)
- `fetchPriority` (`'high' | 'low' | 'auto'`)
- `alt`, `cacheVersion`, `pictureAttr`, `imgAttr`, `prettyPrint`

---

Set loading behavior carefully to avoid writing too many variants during a single page load.
Above-fold:

```php
<?= \Config\Services::superImage()->render([
    'src' => 'hero.jpg',
    'widths' => 'full',
    'loading' => 'eager',
    'fetchPriority' => 'high'
]) ?>
```

Below-fold:

```php
<?= \Config\Services::superImage()->render([
    'src' => 'product_1.jpg',
    'widths' => 'full',
    'loading' => 'lazy',
    'fetchPriority' => 'low',
]) ?>
```

## Resizer utility methods

From `\Config\Services::resizer()`:

- `serve(string $requestPath): void`
- `cleanExpired(): int`
- `cleanImage(string $basePath): int`
- `cleanAll(): int`

#### Testing .htaccess serving

Bring up an image in the browser, so it writes the cache file. Verify file is written.
Comment out the CI rewrite, clear cache, and refresh the browser. You should NOT see X-Superimage-Cache in development a env - this means the file is being served by .htaccess!

#### If you still see X-Superimage-Cache header...

Mess with the DOCUMENT_ROOT paths until it goes away.

## View Examples

### Hero — above the fold, full width

```php
<?= \Config\Services::superImage()->render([
    'src'           => 'hero.jpg',
    'alt'           => 'Hero image',
    'widths'        => 'full',
    'loading'       => 'eager',
    'fetchPriority' => 'high',
]) ?>
```

### Product grid — three columns with gutter

```php
<?php for ($i = 1; $i <= 3; $i++): ?>
    <?= \Config\Services::superImage()->render([
        'src'     => "product_$i.jpg",
        'alt'     => "Product $i",
        'widths'  => 'third',
        'gutter'  => 30,
        'loading' => $i === 1 ? 'eager' : 'lazy',
    ]) ?>
<?php endfor; ?>
```

### Responsive layout — full → half → third across breakpoints

Use `SuperImageWidths` when the image occupies different fractions of the viewport at different screen sizes. Each `at()` call is a `min-width`, matching CSS media query logic.

```php
<?= \Config\Services::superImage()->render([
    'src'     => 'featured.jpg',
    'alt'     => 'Featured article',
    'loading' => 'lazy',
    'widths'  => \Tomkirsch\SuperImage\SuperImageWidths::make()
        ->full()             // 0px+:    100% (mobile)
        ->at(800, 'half')    // 800px+:   50% (tablet)
        ->at(1024, 'third')  // 1024px+:  33% (desktop)
]) ?>
```

Builder output is an `<img>` with width-descriptor candidates from the bounded ladder and
`sizes="(min-width: 1024px) 33.3333vw, (min-width: 800px) 50vw, 100vw"`.
Fractions remain fluid at wider viewports; `px(1200, 360)` can specify a fixed slot.
If every slot is pixel-based (including an explicit mobile `px(0, N)`), candidates honor
`maxResolution` and `resolutionStep`, rounded up to canonical ladder widths.
Fluid or mixed builders use the full safe ladder to cover arbitrary viewport widths; a single
width-descriptor srcset cannot enforce a per-slot DPR ceiling there, so `maxResolution` does
not limit those builders. A single fixed pixel slot has a bounded candidate ceiling; when
multiple different pixel slots share a srcset, candidates are bounded relative to each supplied
slot, but the browser may select a larger slot's candidate. Presets use per-breakpoint density
srcsets when a per-slot ceiling is needed.
Subclasses overriding `resolve(int $gutter = 0): array` retain pixel-map sizes and bounded density candidates.
`gutter` subtracts pixels using `calc()` (clamped to at least 1px).

Raw decimals and `gutter` also work:

```php
'widths' => \Tomkirsch\SuperImage\SuperImageWidths::make()
    ->at(0, 1.0)
    ->at(768, 0.6)
    ->at(1200, 0.4),
'gutter' => 24,
```

### Reuse config across multiple images with `load()`

```php
<?php $si = \Config\Services::superImage()->load([
    'widths'  => 'quarter',
    'loading' => 'lazy',
    'gutter'  => 16,
]); ?>

<?= $si->render(['src' => 'gallery/photo-1.jpg', 'alt' => 'Photo 1']) ?>
<?= $si->render(['src' => 'gallery/photo-2.jpg', 'alt' => 'Photo 2']) ?>
<?= $si->render(['src' => 'gallery/photo-3.jpg', 'alt' => 'Photo 3']) ?>
```

### Static `<img srcset>` for fixed-size images (avatars, icons)

```php
<?= \Config\Services::superImage()->render([
    'src'     => 'avatar.jpg',
    'alt'     => 'User avatar',
    'static'  => true,
    'widths'  => [100, 200, 300],
    'imgAttr' => ['class' => 'rounded-circle'],
]) ?>
```

### Plain URL for CSS backgrounds

```php
$url = \Config\Services::superImage()->imgUrl(800, ['src' => 'banner.jpg']);
```

```html
<div style="background-image: url('<?= $url ?>')"></div>
```
