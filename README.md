# SuperImage - Responsive Images for CodeIgniter 4

A modern, high-performance responsive image library with on-the-fly resizing, hybrid caching, and modern HTML support.

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
    public string $sourcePath = WRITEPATH . 'img'; // where your hires assets live
    public string $cachePath = FCPATH . '_superimage_cache/'; // MUST be in public for direct Apache caching (no PHP overhead)
    public string $servingStrategy = 'htaccess'; // most efficient. falls back to php's readfile()
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
    # Match pattern: img/{anything}-w{number}.{ext}
    # Examples:
    #   /img/image_1-w1200.webp
    #   /img/products/photo-w800.jpg
    #   /img/blog/hero-w1920.png

    # Check if the cached version of the requested image exists
    # We use a look-ahead to capture the groups from the rule below
    RewriteCond %{DOCUMENT_ROOT}/public/_superimage_cache/$1-w$2-v$3.$4 -f

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

## View

The load priority should be carefully set to avoid writing many too many images on a single page load!
Above-fold:

```php
<?= \Config\Services::superImage()->render([
    'src' => 'YOUR_FILE.jpg',
    'widths' => 'full',
    'loading' => 'eager',
    'priority' => 'high'
]) ?>
```

Below-fold:

```php
<?= \Config\Services::superImage()->render([
    'src' => 'YOUR_FILE.jpg',
    'widths' => 'full',
    'loading' => 'lazy',
    'priority'=>'low',
]) ?>
```

#### Testing .htaccess serving

Bring up an image in the browser, so it writes the cache file. Verify file is written.
Comment out the CI rewrite, clear cache, and refresh the browser. You should NOT see X-Superimage-Cache in development a env - this means the file is being served by .htaccess!

#### If you still see X-Superimage-Cache header...

Mess with the DOCUMENT_ROOT paths until it goes away.
