# SuperImage - Responsive Images for CodeIgniter 4

A modern responsive image library for CodeIgniter 4 with on-the-fly resizing, versioned cache filenames, and modern HTML output (`<picture>` / `<img srcset>`). By default (`cacheBustingMethod = 'mtime'`), stale requests are redirected (301) to the current versioned URL.

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
    public string $cachePath = FCPATH . '_superimage_cache/'; // MUST be in public for direct Apache caching (no PHP overhead)
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
    RewriteRule ^img/(.+)-w(\d+)-v(\d+)\.(webp|jpg|png|avif)$ _superimage_cache/$1-w$2-v$3.$4 [L]

    # ============================================================================
    # CACHE HEADERS FOR IMAGES (Optional but Recommended)
    # ============================================================================
    # Sets proper caching headers for image files
    # This works for both cached resizes AND original images
    # ============================================================================

    <IfModule mod_headers.c>
    	# Match image files
    	<FilesMatch "\.(webp|jpg|jpeg|png|gif|avif|ico|svg)$">
    		# Cache for 1 year (images are cache-busted via URL params)
    		Header set Cache-Control "public, max-age=31536000, immutable"

    		# Allow content negotiation (important for webp/avif)
    		Header set Vary "Accept"

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
- `outputExt`
- `widths` (`'full'`, `'half'`, `'third'`, `'quarter'`, `'two-thirds'`, array, or decimal fraction)
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
