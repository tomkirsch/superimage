# SuperImage - Practical Examples

Real-world usage patterns for common scenarios.

## Table of Contents
- [Blog/Article Layout](#blogarticle-layout)
- [E-commerce Product Pages](#e-commerce-product-pages)
- [Portfolio/Gallery](#portfoliogallery)
- [User Avatars](#user-avatars)
- [Responsive Grids](#responsive-grids)
- [Background Images](#background-images)
- [Cache Busting](#cache-busting)
- [Helper Functions](#helper-functions)

---

## Blog/Article Layout

```php
<!-- app/Views/blog/post.php -->

<article class="blog-post">
    <!-- Hero image - full width, high priority -->
    <?= \Config\Services::superimage()->render([
        'src' => WRITEPATH . 'uploads/blog/' . $post['hero'],
        'alt' => $post['title'],
        'widths' => 'full',
        'ratio' => '21:9',
        'eager' => true,
        'priority' => 'high',
        'imgAttr' => ['class' => 'hero-image mb-4']
    ]) ?>

    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <h1><?= esc($post['title']) ?></h1>
                
                <!-- Content with inline images -->
                <?= $post['content'] ?>
                
                <!-- Inline image - 2/3 width -->
                <?= \Config\Services::superimage()->render([
                    'src' => WRITEPATH . 'uploads/blog/' . $post['inline_image'],
                    'alt' => 'Supporting visual',
                    'widths' => 'two-thirds',
                    'lazy' => true,
                    'imgAttr' => ['class' => 'my-4 rounded shadow']
                ]) ?>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <h3>Related Posts</h3>
                <?php foreach ($related as $relatedPost): ?>
                <div class="related-item mb-3">
                    <?= \Config\Services::superimage()->render([
                        'src' => WRITEPATH . 'uploads/blog/' . $relatedPost['thumbnail'],
                        'alt' => $relatedPost['title'],
                        'widths' => 'quarter',
                        'ratio' => '4:3',
                        'lazy' => true,
                        'imgAttr' => ['class' => 'thumbnail']
                    ]) ?>
                    <h4><?= esc($relatedPost['title']) ?></h4>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</article>
```

---

## E-commerce Product Pages

```php
<!-- app/Views/shop/product.php -->

<div class="product-detail">
    <div class="row">
        <!-- Main product image -->
        <div class="col-md-6">
            <?= \Config\Services::superimage()->render([
                'src' => WRITEPATH . 'uploads/products/' . $product['main_image'],
                'alt' => $product['name'],
                'widths' => [
                    1400 => 660,   // xxl: half container
                    1200 => 570,   // xl: half container
                    992 => 480,    // lg: half container
                    768 => 720,    // md: full container
                    0 => 540       // sm: full width
                ],
                'ratio' => '1:1',  // Square product photos
                'priority' => 'high',
                'imgAttr' => [
                    'class' => 'product-main-image',
                    'data-zoom' => 'true'
                ]
            ]) ?>
            
            <!-- Thumbnail gallery -->
            <div class="thumbnail-gallery mt-3">
                <?php foreach ($product['images'] as $idx => $image): ?>
                <div class="thumb-wrapper">
                    <?= \Config\Services::superimage()->render([
                        'src' => WRITEPATH . 'uploads/products/' . $image,
                        'alt' => $product['name'] . ' - View ' . ($idx + 1),
                        'static' => true,
                        'widths' => [80, 160, 240],  // Small thumbnails
                        'ratio' => '1:1',
                        'lazy' => $idx > 0,
                        'maxResolution' => 1.0,  // No retina for thumbnails
                        'imgAttr' => [
                            'class' => 'thumbnail-item',
                            'data-thumb-index' => $idx
                        ]
                    ]) ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Product info -->
        <div class="col-md-6">
            <h1><?= esc($product['name']) ?></h1>
            <p class="price">$<?= number_format($product['price'], 2) ?></p>
            <button class="btn btn-primary">Add to Cart</button>
        </div>
    </div>
    
    <!-- Related products -->
    <div class="related-products mt-5">
        <h2>You May Also Like</h2>
        <div class="row">
            <?php foreach ($related as $item): ?>
            <div class="col-lg-3 col-md-4 col-sm-6">
                <?= \Config\Services::superimage()->render([
                    'src' => WRITEPATH . 'uploads/products/' . $item['thumbnail'],
                    'alt' => $item['name'],
                    'widths' => 'quarter',
                    'ratio' => '1:1',
                    'lazy' => true,
                    'imgAttr' => ['class' => 'product-thumb']
                ]) ?>
                <h3><?= esc($item['name']) ?></h3>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
```

---

## Portfolio/Gallery

```php
<!-- app/Views/portfolio/index.php -->

<div class="portfolio-grid">
    <div class="row">
        <?php foreach ($projects as $project): ?>
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="portfolio-item">
                <?= \Config\Services::superimage()->render([
                    'src' => WRITEPATH . 'uploads/portfolio/' . $project['image'],
                    'alt' => $project['title'],
                    'widths' => 'third',
                    'gutter' => 15,  // Bootstrap default gutter
                    'ratio' => '3:2',
                    'lazy' => true,
                    'imgAttr' => [
                        'class' => 'portfolio-thumb',
                        'data-lightbox' => 'portfolio',
                        'data-title' => $project['title']
                    ]
                ]) ?>
                <div class="portfolio-info">
                    <h3><?= esc($project['title']) ?></h3>
                    <p><?= esc($project['category']) ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Masonry-style grid (custom widths) -->
<div class="masonry-grid">
    <?php foreach ($projects as $idx => $project): ?>
    <?php
    // Vary sizes for visual interest
    $layout = $idx % 3 === 0 ? 'two-thirds' : 'third';
    ?>
    <div class="masonry-item">
        <?= \Config\Services::superimage()->render([
            'src' => WRITEPATH . 'uploads/portfolio/' . $project['image'],
            'alt' => $project['title'],
            'widths' => $layout,
            'lazy' => true,
            'imgAttr' => ['class' => 'masonry-img']
        ]) ?>
    </div>
    <?php endforeach; ?>
</div>
```

---

## User Avatars

```php
<!-- Small avatar (navbar, comments) -->
<?= \Config\Services::superimage()->render([
    'src' => WRITEPATH . 'uploads/avatars/' . $user['avatar'],
    'alt' => $user['name'],
    'static' => true,
    'widths' => [40, 80, 120],
    'ratio' => '1:1',
    'maxResolution' => 2.0,
    'imgAttr' => [
        'class' => 'avatar avatar-sm rounded-circle',
        'style' => 'width: 40px; height: 40px;'
    ]
]) ?>

<!-- Medium avatar (profile sidebar) -->
<?= \Config\Services::superimage()->render([
    'src' => WRITEPATH . 'uploads/avatars/' . $user['avatar'],
    'alt' => $user['name'],
    'static' => true,
    'widths' => [100, 200, 300],
    'ratio' => '1:1',
    'imgAttr' => [
        'class' => 'avatar avatar-md rounded-circle',
        'style' => 'width: 100px; height: 100px;'
    ]
]) ?>

<!-- Large avatar (profile page header) -->
<?= \Config\Services::superimage()->render([
    'src' => WRITEPATH . 'uploads/avatars/' . $user['avatar'],
    'alt' => $user['name'],
    'static' => true,
    'widths' => [150, 300, 450],
    'ratio' => '1:1',
    'imgAttr' => [
        'class' => 'avatar avatar-lg rounded-circle shadow-lg',
        'style' => 'width: 150px; height: 150px;'
    ]
]) ?>
```

---

## Responsive Grids

### Dynamic Grid (Controller Calculates Widths)

```php
// app/Controllers/Gallery.php
class Gallery extends BaseController
{
    public function index()
    {
        $config = config('SuperImageConfig');
        
        // Calculate widths for 3-col → 2-col → 1-col responsive grid
        $widths = [];
        foreach ($config->breakpoints() as $size => $breakpoint) {
            $container = $config->containers()[$size];
            $gutter = 30;
            
            // Determine columns based on breakpoint
            $cols = match(true) {
                $breakpoint >= 992 => 3,   // lg+: 3 columns
                $breakpoint >= 768 => 2,   // md: 2 columns
                default => 1               // sm: 1 column
            };
            
            $widths[$breakpoint] = (int)(($container / $cols) - $gutter);
        }
        $widths[0] = 540;  // Mobile default
        
        return view('gallery/index', [
            'images' => $this->galleryModel->findAll(),
            'imageWidths' => $widths
        ]);
    }
}
```

```php
<!-- app/Views/gallery/index.php -->
<div class="gallery">
    <div class="row">
        <?php foreach ($images as $image): ?>
        <div class="col-lg-4 col-md-6 col-sm-12 mb-4">
            <?= \Config\Services::superimage()->render([
                'src' => $image['path'],
                'alt' => $image['title'],
                'widths' => $imageWidths,
                'ratio' => '4:3',
                'lazy' => true
            ]) ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
```

---

## Background Images

### CSS Background with Responsive Sources

```php
<!-- Generate data attributes for JavaScript -->
<?php
$bgSizes = [540, 720, 960, 1140, 1320];
$bgData = [];
foreach ($bgSizes as $size) {
    $bgData["data-bg-{$size}"] = base_url("uploads/backgrounds/hero-w{$size}.jpg");
}
?>

<div class="hero-section" <?= stringify_attributes($bgData) ?>>
    <div class="hero-content">
        <h1>Welcome</h1>
    </div>
</div>

<script>
// JavaScript to set appropriate background
function setResponsiveBackground(element) {
    const width = window.innerWidth;
    let bgSize;
    
    if (width >= 1400) bgSize = 1320;
    else if (width >= 1200) bgSize = 1140;
    else if (width >= 992) bgSize = 960;
    else if (width >= 768) bgSize = 720;
    else bgSize = 540;
    
    const bgUrl = element.dataset[`bg${bgSize}`];
    element.style.backgroundImage = `url(${bgUrl})`;
}

const hero = document.querySelector('.hero-section');
setResponsiveBackground(hero);
window.addEventListener('resize', () => setResponsiveBackground(hero));
</script>
```

---

## Cache Busting

### Enable in Config

```php
// app/Config/SuperImageConfig.php

// Method 1: File modification time (accurate, automatic)
public bool $enableCacheBusting = true;
public string $cacheBustingMethod = 'mtime';

// Method 2: App version (manual control, fast)
public bool $enableCacheBusting = true;
public string $cacheBustingMethod = 'app';
public string $appVersion = '2.1.0';  // Update when deploying

// Method 3: Random (development only)
public bool $enableCacheBusting = true;
public string $cacheBustingMethod = 'random';
```

### Manual Cache Invalidation

```php
// When updating a specific image
$resizer = new \Tomkirsch\SuperImage\Resizer();
$resizer->cleanImage('products/photo-123');

// In a model after save
class ProductModel extends Model
{
    protected function afterUpdate(array $data)
    {
        if (isset($data['data']['image'])) {
            $resizer = new \Tomkirsch\SuperImage\Resizer();
            $resizer->cleanImage('products/' . $data['id']['image']);
        }
    }
}
```

---

## Helper Functions

### Global Helper

```php
// app/Helpers/image_helper.php
<?php

if (!function_exists('responsive_image')) {
    /**
     * Shorthand for SuperImage render
     */
    function responsive_image(string $src, string $alt = '', array $options = []): string
    {
        return \Config\Services::superimage()->render(array_merge([
            'src' => $src,
            'alt' => $alt
        ], $options));
    }
}

if (!function_exists('image_url')) {
    /**
     * Get direct URL to resized image
     */
    function image_url(string $src, int $width, string $ext = 'webp'): string
    {
        $config = config('SuperImageConfig');
        $pathInfo = pathinfo($src);
        return $config->imageUrlGenerator(
            $pathInfo['dirname'] . '/' . $pathInfo['filename'],
            $pathInfo['extension'],
            $ext,
            $width
        );
    }
}
```

### Usage in Views

```php
<!-- Cleaner syntax -->
<?= responsive_image(WRITEPATH . 'uploads/hero.jpg', 'Hero', [
    'widths' => 'full',
    'ratio' => '16:9'
]) ?>

<!-- Get direct URL -->
<div style="background-image: url(<?= image_url(WRITEPATH . 'uploads/bg.jpg', 1920) ?>)">
    Content
</div>
```

---

## Model-Based Configuration

```php
// app/Models/ImageModel.php
class ImageModel extends Model
{
    /**
     * Get image config based on context
     */
    public static function getConfig(string $context): array
    {
        return match($context) {
            'hero' => [
                'widths' => 'full',
                'ratio' => '21:9',
                'eager' => true,
                'priority' => 'high'
            ],
            'thumbnail' => [
                'static' => true,
                'widths' => [100, 200, 300],
                'ratio' => '1:1',
                'maxResolution' => 1.0
            ],
            'gallery' => [
                'widths' => 'third',
                'ratio' => '4:3',
                'lazy' => true
            ],
            'product' => [
                'widths' => 'half',
                'ratio' => '1:1',
                'lazy' => true
            ],
            default => [
                'widths' => 'full',
                'lazy' => true
            ]
        };
    }
}
```

```php
<!-- In views -->
<?= \Config\Services::superimage()->render(array_merge(
    ImageModel::getConfig('hero'),
    ['src' => $image, 'alt' => $title]
)) ?>
```

---

## Performance Patterns

### Above-the-Fold Priority

```php
<!-- First 3 images: eager, high priority -->
<?php foreach ($images as $idx => $image): ?>
<?= \Config\Services::superimage()->render([
    'src' => $image['path'],
    'alt' => $image['title'],
    'widths' => 'third',
    'eager' => $idx < 3,
    'priority' => $idx === 0 ? 'high' : 'auto',
    'lazy' => $idx >= 3
]) ?>
<?php endforeach; ?>
```

### Conditional Retina

```php
<!-- High-res for hero, standard for others -->
<?= \Config\Services::superimage()->render([
    'src' => $isHero ? $heroImage : $standardImage,
    'alt' => $title,
    'widths' => $isHero ? 'full' : 'half',
    'maxResolution' => $isHero ? 2.0 : 1.0,
    'priority' => $isHero ? 'high' : 'auto'
]) ?>
```

---

These examples cover most real-world scenarios. Mix and match patterns to fit your specific needs!
