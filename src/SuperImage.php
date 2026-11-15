<?php

namespace Tomkirsch\SuperImage;

use CodeIgniter\Images\Image;

/**
 * SuperImage - Responsive image generation library
 * 
 * Generates responsive images with proper srcset/sizes attributes.
 */
class SuperImage
{
	/**
	 * Runtime option keys supported by SuperImage.
	 */
	protected const RUNTIME_OPTION_KEYS = [
		'file',
		'outputExt',
		'widths',
		'gutter',
		'static',
		'maxResolution',
		'resolutionStep',
		'maxWidth',
		'maxHeight',
		'loading',
		'fetchPriority',
		'lqip',
		'alt',
		'cacheVersion',
		'pictureAttr',
		'imgAttr',
		'prettyPrint',
		'origWidth',
		'origHeight',
	];

	/**
	 * LQIP setting to use the source image at the smallest breakpoint width as the placeholder
	 */
	const LQIP_XS = 'xs';

	/**
	 * LQIP setting to use a transparent pixel as a placeholder
	 */
	const LQIP_PIXEL = 'pixel';

	/**
	 * Hires setting that uses the source image's width as the largest possible size
	 */
	const HIRES_SOURCE = 'source';

	/**
	 * Config instance
	 */
	protected SuperImageConfig $config;

	/**
	 * Per-request image metadata cache (keyed by realpath).
	 */
	protected static array $imageMetaCache = [];

	/**
	 * Options loaded for chained calls
	 */
	protected array $loadedOptions = [];

	/**
	 * Relative base filename WITH extension (e.g., 'hero.jpg' or 'products/product_1.jpg')
	 */
	protected string $file;

	/**
	 * Source image width/height
	 */
	protected ?int $origWidth = null;
	protected ?int $origHeight = null;

	/**
	 * Output file extension
	 */
	protected string $outputExt = 'webp';

	/**
	 * Layout widths configuration
	 */
	protected $widths = 'full';

	/**
	 * Gutter/padding to subtract from container widths
	 */
	protected int $gutter = 0;

	/**
	 * Whether this is a static image (img with srcset) vs dynamic (picture with sources)
	 */
	protected bool $static = false;

	/**
	 * Maximum resolution factor for retina displays
	 */
	protected float $maxResolution = 2.0;

	/**
	 * Resolution step increment
	 */
	protected float $resolutionStep = 0.5;

	/**
	 * Maximum width constraint
	 */
	protected $maxWidth = self::HIRES_SOURCE;

	/**
	 * Maximum height constraint
	 */
	protected $maxHeight = self::HIRES_SOURCE;

	/**
	 * Loading attribute: 'lazy' | 'eager' | 'auto'
	 */
	protected string $loading = 'auto';

	/**
	 * Fetch priority: 'high' | 'low' | 'auto'
	 */
	protected string $fetchPriority = 'auto';

	/**
	 * Low quality image placeholder
	 */
	protected $lqip = null;

	/**
	 * Alt text
	 */
	protected string $alt = '';

	/**
	 * Force cache version
	 */
	protected ?string $cacheVersion = null;

	/**
	 * Custom attributes for picture element
	 */
	protected array $pictureAttr = [];

	/**
	 * Custom attributes for img element
	 */
	protected array $imgAttr = [];

	/**
	 * Pretty print HTML with newlines
	 */
	protected bool $prettyPrint = false;

	/**
	 * Calculated resolution dictionary
	 */
	protected ?array $resolutionDict = null;

	/**
	 * Whether to use placeholder image due to error
	 */
	protected bool $usePlaceholder = false;

	/**
	 * Reason for placeholder image
	 */
	protected ?string $placeholderReason = null;
	/**
	 * Constructor
	 */
	public function __construct(?SuperImageConfig $config = null)
	{
		$this->config = $config ?? new SuperImageConfig();
	}

	/**
	 * Optional, load options for chained calls without triggering image reads
	 */
	public function load(array $options = []): self
	{
		$this->loadedOptions = $this->normalizeOptions($options);
		return $this;
	}

	/**
	 * Main render method - renders responsive image based on configuration
	 * 
	 * @param array $options Configuration array
	 * @return string HTML output
	 */
	public function render(array $options = []): string
	{
		$this->reset();
		$this->applyResolvedOptions($this->resolveOptions($options));
		$this->validate();
		$this->prepare();

		if ($this->usePlaceholder) {
			return $this->renderPlaceholder();
		}

		if ($this->static || $this->isFixedWidthList() || $this->widths instanceof SuperImageWidths) {
			return $this->renderStatic();
		} else {
			return $this->renderDynamic();
		}
	}

	/**
	 * Utility - get a single image URL (optionally for a specific width)
	 */
	public function imgUrl(?int $width = null, array $options = []): string
	{
		$this->reset();
		$this->applyResolvedOptions($this->resolveOptions($options));
		$this->validate();

		if ($width === null) {
			$this->prepare();
			if ($this->usePlaceholder) {
				return '';
			}
			$fallbackWidth = min(array_merge(...array_values($this->resolutionDict)));
			return $this->getImageUrl($fallbackWidth);
		}
		return $this->getImageUrl($width);
	}

	/**
	 * Render placeholder image SVG
	 */
	protected function renderPlaceholder(): string
	{
		$fullPath = $this->config->getSourcePath($this->file);
		$reason = $this->placeholderReason ?? "Image not found: {$fullPath}";

		return sprintf(
			'<svg width="100%%" height="200" xmlns="http://www.w3.org/2000/svg">
            <rect width="100%%" height="100%%" fill="#f8f9fa"/>
            <text x="50%%" y="50%%" text-anchor="middle" fill="#6c757d" font-family="sans-serif">
                %s
            </text>
        </svg>',
			htmlspecialchars($reason)
		);
	}

	/**
	 * Reset to defaults
	 */
	protected function reset(): void
	{
		$this->origWidth = null;
		$this->origHeight = null;
		$this->applyResolvedOptions($this->config->renderDefaults());
		$this->resolutionDict = null;
		$this->usePlaceholder = false;
		$this->placeholderReason = null;
	}

	/**
	 * Resolve runtime options with precedence:
	 * defaults < loaded options < render options.
	 */
	protected function resolveOptions(array $options): array
	{
		$loaded = $this->normalizeOptions($this->loadedOptions);
		$runtime = $this->normalizeOptions($options);

		return array_replace($this->config->renderDefaults(), $loaded, $runtime);
	}

	/**
	 * Normalize and validate option keys.
	 */
	protected function normalizeOptions(array $options): array
	{
		$normalized = [];

		foreach ($options as $key => $value) {
			$normalizedKey = $key === 'src' ? 'file' : $key;
			if (!in_array($normalizedKey, self::RUNTIME_OPTION_KEYS, true)) {
				throw new \InvalidArgumentException("Unknown SuperImage option: {$key}");
			}
			$normalized[$normalizedKey] = $value;
		}

		return $normalized;
	}

	/**
	 * Apply resolved runtime options to object properties.
	 */
	protected function applyResolvedOptions(array $options): void
	{
		foreach (self::RUNTIME_OPTION_KEYS as $key) {
			if (array_key_exists($key, $options)) {
				$this->$key = $options[$key];
			}
		}
	}

	/**
	 * Validate configuration
	 */
	protected function validate(): void
	{
		if (!is_finite($this->resolutionStep) || $this->resolutionStep <= 0
			|| !is_finite($this->maxResolution) || $this->maxResolution < 1
			|| ($this->maxResolution - 1) / $this->resolutionStep > 1000) {
			throw new \InvalidArgumentException('Require resolutionStep > 0, maxResolution >= 1 and at most 1000 resolution increments');
		}
		if (empty($this->file)) {
			throw new \Exception('No source file specified. Use "src" or "file" option.');
		}
	}

	/**
	 * Prepare image data and calculate dimensions
	 */
	protected function prepare(): void
	{
		$this->loadImage();
		if ($this->usePlaceholder) {
			return;
		}
		$layoutWidths = $this->calculateLayoutWidths();
		$this->buildResolutionDict($layoutWidths);
	}

	/**
	 * Load image file and extract dimensions
	 */
	protected function loadImage(): void
	{
		if ($this->origWidth && $this->origHeight) {
			return;
		}

		$sourcePath = $this->config->getSourcePath($this->file);
		$cacheKey = $this->getImageCacheKey($sourcePath);

		if (isset(self::$imageMetaCache[$cacheKey])) {
			$meta = self::$imageMetaCache[$cacheKey];
			$this->origWidth = $meta['width'];
			$this->origHeight = $meta['height'];
			return;
		}

		try {
			$image = new Image($sourcePath, false);
			if (!$this->origWidth || !$this->origHeight) {
				$props = $image->getProperties(true);
				$this->origWidth = $props['width'];
				$this->origHeight = $props['height'];
			}
			self::$imageMetaCache[$cacheKey] = [
				'width' => $this->origWidth,
				'height' => $this->origHeight,
			];
		} catch (\Exception $e) {
			// Image doesn't exist or is invalid
			$this->usePlaceholder = true;
			$this->placeholderReason = "Failed to load image {$sourcePath}: " . $e->getMessage();
		}
	}

	/**
	 * Normalize a cache key for the image metadata cache
	 */
	protected function getImageCacheKey(string $file): string
	{
		$real = realpath($file);
		return $real ?: $file;
	}

	/**
	 * Calculate layout widths based on configuration
	 */
	protected function calculateLayoutWidths(): array
	{
		// fluent builder
		if ($this->widths instanceof SuperImageWidths) {
			return $this->widths->resolve($this->gutter);
		}

		if (is_string($this->widths)) {
			return $this->getPresetWidths($this->widths);
		}

		if (is_float($this->widths) && $this->widths <= 1) {
			return $this->getPercentageWidths($this->widths);
		}

		if (is_array($this->widths) && $this->widths !== []) {
			$widths = $this->widths;
			if ($this->isFixedWidthList()) {
				sort($widths);
			} else {
				krsort($widths);
			}
			return $widths;
		}

		throw new \Exception('Invalid widths configuration');
	}

	/**
	 * Get preset layout widths
	 */
	protected function getPresetWidths(string $preset): array
	{
		$containers = $this->config->containers();
		$breakpoints = $this->config->breakpoints();

		$widths = [];

		$fraction = match ($preset) {
			'full' => 1.0,
			'half' => 0.5,
			'third' => 1 / 3,
			'quarter' => 0.25,
			'two-thirds' => 2 / 3,
			default => throw new \Exception("Unknown preset: $preset")
		};

		foreach ($breakpoints as $size => $breakpoint) {
			$containerWidth = $containers[$size];
			$imageWidth = (int)($containerWidth * $fraction) - $this->gutter;
			$widths[$breakpoint] = max(1, $imageWidth);
		}

		$widths[0] = max(1, (int)(min($containers) * $fraction) - $this->gutter);
		krsort($widths);

		return $widths;
	}

	/**
	 * Get percentage-based widths
	 */
	protected function getPercentageWidths(float $percentage): array
	{
		$containers = $this->config->containers();
		$breakpoints = $this->config->breakpoints();

		$widths = [];

		foreach ($breakpoints as $size => $breakpoint) {
			$containerWidth = $containers[$size];
			$imageWidth = (int)($containerWidth * $percentage) - $this->gutter;
			$widths[$breakpoint] = max(1, $imageWidth);
		}

		$widths[0] = max(1, (int)(min($containers) * $percentage) - $this->gutter);
		krsort($widths);

		return $widths;
	}

	/**
	 * Build resolution dictionary for srcset generation
	 */
	protected function buildResolutionDict(array $layoutWidths): void
	{
		$maxWidth = $this->maxCandidateWidth();

		$this->resolutionDict = [];

		foreach ($layoutWidths as $viewportWidth => $imageWidth) {
			$this->resolutionDict[$viewportWidth] = [];

			$steps = (int)floor(($this->maxResolution - 1) / $this->resolutionStep + 1e-9);
			for ($step = 0; $step <= $steps; $step++) {
				$res = 1 + $step * $this->resolutionStep;
				$targetWidth = max(1, (int)min($maxWidth, floor($imageWidth * $res)));

				$resKey = (string)$res; // ensure 1.5 becomes '1.5' key
				$this->resolutionDict[$viewportWidth][$resKey] = $targetWidth;
			}
		}

		if (!isset($this->resolutionDict[0]) || empty($this->resolutionDict[0])) {
			$this->resolutionDict[0] = ['1' => max(1, min($maxWidth, 540))];
		}
	}

	/**
	 * Resize maintaining aspect ratio
	 */
	protected function reproportion(int $width, int $height = 0): array
	{
		if ($height === 0) {
			$height = max(1, (int)floor($width * $this->origHeight / $this->origWidth));
		} else {
			$width = (int)floor($this->origWidth * $height / $this->origHeight);
		}
		return [$width, $height];
	}

	/**
	 * Render dynamic image (picture element)
	 */
	protected function renderDynamic(): string
	{
		$nl = $this->prettyPrint ? "\n" : '';
		$out = '';

		$pictureAttrStr = $this->stringifyAttributes($this->pictureAttr);
		$out .= "<picture{$pictureAttrStr}>{$nl}";

		foreach ($this->resolutionDict as $viewportWidth => $resolutions) {
			if ($viewportWidth === 0 || empty($resolutions)) {
				continue;
			}

			$srcset = $seen = [];
			foreach ($resolutions as $res => $width) {
				$url = $this->getImageUrl($width);
				$descriptor = floatval($res) > 1 ? " {$res}x" : '';
				if (in_array($url, $seen, true)) {
					continue; // snapped to a width already listed
				}
				$seen[] = $url;
				$srcset[] = $url . $descriptor;
			}

			$srcsetStr = implode(', ', $srcset);
			if ($srcsetStr === '') {
				continue;
			}

			$media = "(min-width: {$viewportWidth}px)";

			$sourceAttr = [
				'media' => $media,
				'srcset' => $srcsetStr
			];

			if (in_array($this->outputExt, ['webp', 'avif'])) {
				$sourceAttr['type'] = 'image/' . $this->outputExt;
			}

			$sourceAttrStr = $this->stringifyAttributes($sourceAttr);
			$out .= "  <source{$sourceAttrStr}>{$nl}";
		}

		$out .= $this->renderImgElement();
		$out .= "</picture>{$nl}";

		return $out;
	}

	/**
	 * Render static image (img element with srcset)
	 */
	protected function renderStatic(): string
	{
		return $this->renderImgElement(true);
	}

	/**
	 * Render img element
	 */
	protected function renderImgElement(bool $includeSrcset = false): string
	{
		$nl = $this->prettyPrint ? "\n" : '';
		$attr = $this->imgAttr;

		$widths = [];
		array_walk_recursive($this->resolutionDict, function ($v) use (&$widths) {
			$widths[] = $v;
		});
		if (empty($widths)) {
			$widths[] = 540; // fallback width
		}
		$fallbackWidth = reset($this->resolutionDict[0]);
		$attr['src'] = $this->getImageUrl($fallbackWidth);

		$attr['alt'] = $this->alt;

		$attr['width'] = $this->origWidth;
		$attr['height'] = $this->origHeight;
		if ($this->isFixedWidthList()) {
			[$attr['width'], $attr['height']] = $this->reproportion(max(1, (int)min($this->widths)));
		}

		if ($this->loading !== 'auto') {
			$attr['loading'] = $this->loading;
		}

		if ($this->fetchPriority !== 'auto') {
			$attr['fetchpriority'] = $this->fetchPriority;
		}

		if ($includeSrcset) {
			$srcset = [];
			$allWidths = [];

			foreach ($this->resolutionDict as $resolutions) {
				foreach ($resolutions as $width) {
					$allWidths[] = $width;
				}
			}
			if ($this->widths instanceof SuperImageWidths) {
				// Fluid slots need the whole bounded ladder, not widths sampled at breakpoints.
				$cap = $this->snapWidth($this->maxCandidateWidth());
				$allWidths = array_merge($this->config->widthLadder, [$cap]);
				$allWidths = array_map(fn($w) => min($cap, $w), $allWidths);
			}
			$allWidths = array_unique(array_map(fn($w) => $this->snapWidth($w), $allWidths));
			sort($allWidths);

			foreach ($allWidths as $width) {
				$url = $this->getImageUrl($width);
				$srcset[] = "{$url} {$width}w";
			}

			$attr['srcset'] = implode(', ', $srcset);
			$attr['sizes'] = $this->generateSizesAttribute();
		} else {
			// Below the first picture source, phones still need high-DPR candidates.
			$srcset = $seen = [];
			foreach ($this->resolutionDict[0] as $res => $width) {
				$url = $this->getImageUrl($width);
				if (!in_array($url, $seen, true)) {
					$seen[] = $url;
					$srcset[] = $url . ' ' . $res . 'x';
				}
			}
			$attr['srcset'] = implode(', ', $srcset);
		}

		$attrStr = $this->stringifyAttributes($attr);
		return "  <img{$attrStr}>{$nl}";
	}

	/**
	 * Generate sizes attribute for responsive images
	 */
	protected function generateSizesAttribute(): string
	{
		if ($this->isFixedWidthList()) {
			return max(1, (int)min($this->widths)) . 'px';
		}
		if ($this->widths instanceof SuperImageWidths) {
			return $this->widths->sizes($this->gutter);
		}
		$sizes = [];

		foreach ($this->resolutionDict as $viewportWidth => $resolutions) {
			if ($viewportWidth === 0 || empty($resolutions)) {
				continue;
			}

			$imageWidth = reset($resolutions);
			$sizes[] = "(min-width: {$viewportWidth}px) {$imageWidth}px";
		}

		$defaultWidth = reset($this->resolutionDict[0]);
		$sizes[] = "{$defaultWidth}px";

		return implode(', ', $sizes);
	}

	protected function isFixedWidthList(): bool
	{
		return is_array($this->widths) && $this->widths !== []
			&& array_keys($this->widths) === range(0, count($this->widths) - 1);
	}

	protected function maxCandidateWidth(): int
	{
		$width = $this->origWidth;
		if (is_numeric($this->maxWidth)) {
			$width = min($width, (int)$this->maxWidth);
		}
		if (is_numeric($this->maxHeight)) {
			$width = min($width, (int)floor((int)$this->maxHeight * $this->origWidth / $this->origHeight));
		}
		return max(1, min($width, $this->config->snapWidth(PHP_INT_MAX, $this->origWidth)));
	}

	protected function snapWidth(int $width): int
	{
		return $this->origWidth ? $this->config->snapWidth($width, $this->origWidth) : $width;
	}

	/**
	 * Generate image URL
	 */
	protected function getImageUrl(int $width): string
	{
		// Snap to the width ladder so the URL is served without a redirect
		if (!$this->origWidth) {
			$this->loadImage();
		}
		$width = $this->origWidth
			? $this->config->snapWidth($width, $this->origWidth)
			: min($width, $this->config->maxSize);

		return $this->config->imageUrl(
			$this->file,
			$width,
			$this->cacheVersion,
			$this->outputExt,
		);
	}

	/**
	 * Stringify HTML attributes
	 */
	protected function stringifyAttributes(array $attr): string
	{
		if (empty($attr)) {
			return '';
		}

		$str = '';
		foreach ($attr as $key => $value) {
			if ($value === null || $value === false) {
				continue;
			}
			if ($value === true) {
				$str .= " {$key}";
			} else {
				$escaped = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
				$str .= " {$key}=\"{$escaped}\"";
			}
		}
		return $str;
	}

	/**
	 * Transparent pixel data URI
	 */
	public function pixel64(): string
	{
		return 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
	}

	/**
	 * Get the list of images read during this request
	 */
	public function getReadImages(): array
	{
		return self::$imageMetaCache;
	}
}
