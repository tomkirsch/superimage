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
	 * Source image file path
	 */
	protected string $file;

	/**
	 * Source Image instance
	 */
	protected ?Image $image = null;

	/**
	 * Source image width/height
	 */
	protected ?int $origWidth = null;
	protected ?int $origHeight = null;

	/**
	 * Public-facing filename (without extension)
	 */
	protected ?string $publicFile = null;

	/**
	 * Output file extension
	 */
	protected string $outputExt = 'webp';

	/**
	 * Original file extension
	 */
	protected ?string $originalExt = null;

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
	 * Main render method - renders responsive image based on configuration
	 * 
	 * @param array $options Configuration array
	 * @return string HTML output
	 */
	public function render(array $options = []): string
	{
		$this->reset();
		$this->applyOptions($options);
		$this->validate();
		$this->prepare();

		if ($this->usePlaceholder) {
			return $this->renderPlaceholder();
		}

		if ($this->static) {
			return $this->renderStatic();
		} else {
			return $this->renderDynamic();
		}
	}

	/**
	 * Render placeholder image SVG
	 */
	protected function renderPlaceholder(): string
	{
		// if development mode, show full path, otherwise basename only
		// use CI_ENVIRONMENT constant if defined
		$displayFile = (env('CI_ENVIRONMENT') === 'development') ? $this->file : basename($this->file);
		// Return SVG placeholder with error message
		return sprintf(
			'<svg width="100%%" height="200" xmlns="http://www.w3.org/2000/svg">
            <rect width="100%%" height="100%%" fill="#f8f9fa"/>
            <text x="50%%" y="50%%" text-anchor="middle" fill="#6c757d" font-family="sans-serif">
                Image not found: %s
            </text>
        </svg>',
			htmlspecialchars($displayFile)
		);
	}

	/**
	 * Reset to defaults
	 */
	protected function reset(): void
	{
		$this->file = '';
		$this->image = null;
		$this->origWidth = null;
		$this->origHeight = null;
		$this->publicFile = null;
		$this->originalExt = null;
		$this->outputExt = $this->config->defaultOutputExt;
		$this->widths = 'full';
		$this->gutter = 0;
		$this->static = false;
		$this->maxResolution = $this->config->defaultMaxResolution;
		$this->resolutionStep = $this->config->defaultResolutionStep;
		$this->maxWidth = self::HIRES_SOURCE;
		$this->maxHeight = self::HIRES_SOURCE;
		$this->loading = $this->config->defaultLoading;
		$this->fetchPriority = $this->config->defaultFetchPriority;
		$this->lqip = $this->config->defaultLqip;
		$this->alt = '';
		$this->pictureAttr = [];
		$this->imgAttr = [];
		$this->prettyPrint = $this->config->prettyPrint;
		$this->resolutionDict = null;
		$this->usePlaceholder = false;
		$this->placeholderReason = null;
	}

	/**
	 * Apply options from config array
	 */
	protected function applyOptions(array $options): void
	{
		foreach ($options as $key => $value) {
			switch ($key) {
				case 'src':
					$this->file = $value;
					break;
				case 'lazy':
					$this->loading = $value ? 'lazy' : 'auto';
					break;
				case 'eager':
					$this->loading = $value ? 'eager' : 'auto';
					break;
				case 'priority':
					$this->fetchPriority = $value;
					break;
				default:
					if (property_exists($this, $key)) {
						$this->$key = $value;
					}
			}
		}
	}

	/**
	 * Validate configuration
	 */
	protected function validate(): void
	{
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
		$this->parseFilePath();
		$layoutWidths = $this->calculateLayoutWidths();
		$this->buildResolutionDict($layoutWidths);
	}

	/**
	 * Load image file and extract dimensions
	 */
	protected function loadImage(): void
	{
		if ($this->image && $this->origWidth && $this->origHeight) {
			return;
		}
		try {
			$this->image = new Image($this->file, false);
			if (!$this->origWidth || !$this->origHeight) {
				$props = $this->image->getProperties(true);
				$this->origWidth = $props['width'];
				$this->origHeight = $props['height'];
			}
		} catch (\Exception $e) {
			// Image doesn't exist or is invalid
			// Return placeholder image HTML instead of throwing
			$this->usePlaceholder = true;
			$this->placeholderReason = $e->getMessage();
		}
	}

	/**
	 * Parse file path to extract name and extension
	 */
	protected function parseFilePath(): void
	{
		$pathInfo = pathinfo($this->image->getPathname());
		$this->originalExt = $pathInfo['extension'];
		$this->publicFile = $pathInfo['filename']; // publicUrlPrefix is added in URL generator
	}

	/**
	 * Calculate layout widths based on configuration
	 */
	protected function calculateLayoutWidths(): array
	{
		if (is_string($this->widths)) {
			return $this->getPresetWidths($this->widths);
		}

		if (is_float($this->widths) && $this->widths <= 1) {
			return $this->getPercentageWidths($this->widths);
		}

		if ($this->static && is_array($this->widths) && isset($this->widths[0])) {
			return $this->widths;
		}

		if (is_array($this->widths)) {
			return $this->widths;
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
			$widths[$breakpoint] = $imageWidth;
		}

		$widths[0] = (int)(min($containers) * $fraction) - $this->gutter;
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
			$widths[$breakpoint] = $imageWidth;
		}

		$widths[0] = (int)(min($containers) * $percentage) - $this->gutter;
		krsort($widths);

		return $widths;
	}

	/**
	 * Build resolution dictionary for srcset generation
	 */
	protected function buildResolutionDict(array $layoutWidths): void
	{
		if ($this->maxWidth === self::HIRES_SOURCE) {
			$maxWidth = $this->origWidth;
		} elseif (is_numeric($this->maxWidth)) {
			$maxWidth = min($this->origWidth, (int)$this->maxWidth);
		} else {
			$maxWidth = $this->origWidth;
		}

		if ($this->maxHeight === self::HIRES_SOURCE) {
			$maxHeight = $this->origHeight;
		} elseif (is_numeric($this->maxHeight)) {
			$maxHeight = (int)$this->maxHeight;
		} else {
			$maxHeight = null;
		}

		$this->resolutionDict = [];

		foreach ($layoutWidths as $viewportWidth => $imageWidth) {
			$this->resolutionDict[$viewportWidth] = [];

			for ($res = 1; $res <= $this->maxResolution; $res += $this->resolutionStep) {
				$targetWidth = (int)floor($imageWidth * $res);

				if ($targetWidth > $maxWidth) {
					continue;
				}

				if ($maxHeight) {
					list($constrainedWidth, $constrainedHeight) = $this->reproportion($targetWidth, 0);
					if ($constrainedHeight > $maxHeight) {
						continue;
					}
				}
				$resKey = (string)$res; // ensure 1.5 becomes '1.5' key
				$this->resolutionDict[$viewportWidth][$resKey] = $targetWidth;
			}
		}

		if (!isset($this->resolutionDict[0])) {
			$this->resolutionDict[0] = ['1' => min($maxWidth, 540)];
		}
	}

	/**
	 * Resize maintaining aspect ratio
	 */
	protected function reproportion(int $width, int $height = 0): array
	{
		if ($height === 0) {
			$height = (int)floor($width * $this->origHeight / $this->origWidth);
		} else {
			$width = (int)floor($this->origWidth * $height / $this->origHeight);
		}
		return [$width, $height];
	}

	/**
	 * Render dynamic responsive image (picture element)
	 */
	protected function renderDynamic(): string
	{
		$nl = $this->prettyPrint ? "\n" : '';
		$out = '';

		$pictureAttrStr = $this->stringifyAttributes($this->pictureAttr);
		$out .= "<picture{$pictureAttrStr}>{$nl}";

		foreach ($this->resolutionDict as $viewportWidth => $resolutions) {
			if ($viewportWidth === 0) {
				continue;
			}

			$srcset = [];
			foreach ($resolutions as $res => $width) {
				$url = $this->getImageUrl($width);
				$descriptor = floatval($res) > 1 ? " {$res}x" : '';
				$srcset[] = $url . $descriptor;
			}

			$srcsetStr = implode(', ', $srcset);
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

		$fallbackWidth = min(array_merge(...array_values($this->resolutionDict)));
		$attr['src'] = $this->getImageUrl($fallbackWidth);

		$attr['alt'] = $this->alt;

		$attr['width'] = $this->origWidth;
		$attr['height'] = $this->origHeight;

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
			$allWidths = array_unique($allWidths);
			sort($allWidths);

			foreach ($allWidths as $width) {
				$url = $this->getImageUrl($width);
				$srcset[] = "{$url} {$width}w";
			}

			$attr['srcset'] = implode(', ', $srcset);
			$attr['sizes'] = $this->generateSizesAttribute();
		}

		$attrStr = $this->stringifyAttributes($attr);
		return "  <img{$attrStr}>{$nl}";
	}

	/**
	 * Generate sizes attribute for responsive images
	 */
	protected function generateSizesAttribute(): string
	{
		$sizes = [];

		foreach ($this->resolutionDict as $viewportWidth => $resolutions) {
			if ($viewportWidth === 0) {
				continue;
			}

			$imageWidth = reset($resolutions);
			$sizes[] = "(min-width: {$viewportWidth}px) {$imageWidth}px";
		}

		$defaultWidth = reset($this->resolutionDict[0]);
		$sizes[] = "{$defaultWidth}px";

		return implode(', ', $sizes);
	}

	/**
	 * Generate image URL
	 */
	protected function getImageUrl(int $width): string
	{
		return $this->config->imageUrlGenerator(
			$this->publicFile,
			$this->originalExt,
			$this->outputExt,
			$width
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
}
