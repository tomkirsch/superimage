# SuperImage - Practical Examples

Real-world usage patterns for common scenarios.

---

## Quick Cheat Sheet

These mirror the test page examples and are meant as a fast reference.

### Generate Public URL from Source + Width

```php
<?php
$url = \Config\Services::superImage(['file' => WRITEPATH . 'img/hero.jpg'])
    ->imgUrl(1200);
```

### Static Image (srcset on img)

```php
<?= \Config\Services::superImage()->render([
    'src' => WRITEPATH . 'img/product_4.jpg',
    'alt' => 'User avatar',
    'static' => true,
    'widths' => [100, 200, 300],
    'imgAttr' => ['class' => 'rounded-circle'],
    'loading' => 'lazy',
]) ?>
```

### Full Width Hero (high priority)

```php
<?= \Config\Services::superImage()->render([
    'src' => WRITEPATH . 'img/hero.jpg',
    'alt' => 'Full width hero',
    'widths' => 'full',
    'loading' => 'eager',
    'priority' => 'high',
]) ?>
```

### Half Width Image (lazy)

```php
<?= \Config\Services::superImage()->render([
    'src' => WRITEPATH . 'img/product_1.jpg',
    'alt' => 'Half width image',
    'widths' => 'half',
    'loading' => 'lazy',
]) ?>
```

### Three Column Grid (square crop)

```php
<?php for ($i = 1; $i <= 3; $i++): ?>
    <?= \Config\Services::superImage()->render([
        'src' => WRITEPATH . "img/product_{$i}.jpg",
        'alt' => "Product {$i}",
        'pictureAttr' => ['style' => 'aspect-ratio: 1/1; overflow: hidden;'],
        'imgAttr' => ['style' => 'object-fit: cover; width: 100%; height: 100%;'],
        'widths' => 'third',
        'loading' => $i > 1 ? 'lazy' : 'eager',
        'priority' => $i === 1 ? 'high' : 'auto',
        'gutter' => 15,
    ]) ?>
<?php endfor; ?>
```

### Custom Width Map

```php
<?= \Config\Services::superImage()->render([
    'src' => WRITEPATH . 'img/product_2.jpg',
    'alt' => 'Custom layout',
    'widths' => [
        1400 => 660,
        1200 => 570,
        992 => 480,
        768 => 360,
        0 => 540,
    ],
]) ?>
```

### Percentage-Based Width (33%)

```php
<?= \Config\Services::superImage()->render([
    'src' => WRITEPATH . 'img/product_3.jpg',
    'alt' => 'Thumbnail',
    'widths' => 0.33,
    'gutter' => 24,
    'loading' => 'lazy',
    'priority' => 'low',
]) ?>
```
