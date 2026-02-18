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
