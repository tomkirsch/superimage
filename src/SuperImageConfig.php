<?php

namespace Tomkirsch\SuperImage;

use CodeIgniter\Config\BaseConfig;

/**
 * SuperImage configuration.
 *
 * Public properties are used for configuration and can be overridden in app config.
 */
class SuperImageConfig extends BaseConfig
{
	/**
	 * Pretty print generated HTML.
	 */
	public bool $prettyPrint = false;
	/**
	 * Default output image extension.
	 */
	public string $defaultOutputExt = 'webp';

	/**
	 * Container widths by breakpoint name.
	 *
	 * @var array<string,int>
	 */
	public array $containers = [
		'xxl' => 1320,
		'xl' => 1140,
		'lg' => 960,
		'md' => 720,
		'sm' => 540,
	];

	/**
	 * Breakpoints by name.
	 *
	 * @var array<string,int>
	 */
	public array $breakpoints = [
		'xxl' => 1400,
		'xl' => 1200,
		'lg' => 992,
		'md' => 768,
		'sm' => 576,
	];

	/**
	 * Default maximum resolution multiplier (e.g. 2.0 for retina).
	 */
	public float $defaultMaxResolution = 2.0;
	/**
	 * Default resolution step size.
	 */
	public float $defaultResolutionStep = 0.5;
	/**
	 * Default loading attribute value: 'lazy' | 'eager' | 'auto'.
	 */
	public string $defaultLoading = 'auto';
	/**
	 * Default fetchpriority attribute value: 'high' | 'low' | 'auto'.
	 */
	public string $defaultFetchPriority = 'auto';
	/**
	 * Default LQIP setting (null disables).
	 */
	public ?string $defaultLqip = null;

	// =========================================================================
	// FILE PATHS
	// =========================================================================

	/**
	 * Source image base directory - this gets prepended to all image file paths.
	 */
	public string $sourcePath = WRITEPATH . 'img/';
	/**
	 * Cache directory for resized images.
	 */
	public string $cachePath = FCPATH . '_superimage_cache/';
	/**
	 * Public URL prefix for generated images.
	 */
	public string $publicUrlPrefix = 'img/';

	// =========================================================================
	// SERVING STRATEGY
	// =========================================================================

	/**
	 * Serving strategy: 'htaccess' or other configured value.
	 */
	public string $servingStrategy = 'htaccess';

	/**
	 * Now forced to true for the filename-based versioning logic.
	 */
	/**
	 * Enable filename-based cache busting.
	 */
	public bool $enableCacheBusting = true;

	/**
	 * Cache busting method: 'mtime' | 'time' | 'app'.
	 */
	public string $cacheBustingMethod = 'mtime';
	/**
	 * App version when using the 'app' busting method.
	 */
	public string $appVersion = '1';

	// =========================================================================
	// RESIZING CONSTRAINTS
	// =========================================================================

	/**
	 * Allow upscaling smaller images.
	 */
	public bool $allowUpscale = false;
	/**
	 * Maximum resize width in pixels.
	 */
	public int $maxSize = 3000;
	/**
	 * Cache TTL in seconds (0 disables expiration cleanup).
	 */
	public int $cacheTTL = 0; 

    // =========================================================================
    // METHODS
    // =========================================================================

	/**
	 * File save hook - called after a resized image is saved.
	 * @param string $filePath Full path to the saved file.
	 * @param object $request Object with properties: basePath, width, version, outputExt.
	 */
	public function onFileSave(string $filePath, object $request): void
	{
		/**
		 * Override in subclass if needed.
		 * Example:
		 * 
		 * // Update file mtime to match source file for DB images
		 * touch($filePath, $request->version);
		 */
	}

	/**
	 * Generate the public-facing image URL with versioning in the filename
	 * Pattern: path/to/file.jpg-w600-v12345678.webp
	 *
	 * @param string $filePath Base path relative to sourcePath.
	 * @param int $width Target width in pixels.
	 * @param string|null $outputExt Output file extension.
	 */
	public function imageUrl(string $filePath, int $width, ?string $cacheVersion = null, ?string $outputExt = null): string
	{
		$request = (object)[
			'basePath' => $filePath,
			'width' => $width,
			'outputExt' => $outputExt ?? $this->defaultOutputExt,
		];
		$version = $this->getCacheVersion($request, $cacheVersion);
		$outputExt = $outputExt ?? $this->defaultOutputExt;

		// Construct filename: filename-w{width}-v{version}.{ext}
		$filename = "{$filePath}-w{$width}-v{$version}.{$outputExt}";

		return base_url("{$this->publicUrlPrefix}{$filename}");
	}

	/**
	 * Prepares the cache path and cleans up old versions of THIS specific image/width.
	 * Called by Resizer before saving a new image.
	 */
	/**
	 * Prepares the cache path and cleans up old versions of this image/width.
	 *
	 * @param object $request Expected properties: basePath, width, version, outputExt.
	 */
	public function prepareCachePath(object $request): string
	{
		// 1. Build the path for the CURRENT version
		// path/to/image.jpg-w600-v134342433.webp
		$newCachePath = rtrim($this->cachePath, '/') . '/' . $request->basePath . "-w" . $request->width . "-v" . $request->version . "." . $request->outputExt;

		// 2. Targeted Cleanup
		// Look for any other -v* files for this specific image and width
		$pattern = rtrim($this->cachePath, '/') . '/' . $request->basePath . "-w" . $request->width . "-v*." . $request->outputExt;

		foreach (glob($pattern) as $file) {
			// Only delete if it's not the one we are currently building
			if ($file !== $newCachePath) {
				@unlink($file);
			}
		}

		return $newCachePath;
	}

	/**
	 * Get the version string based on method
	 */
	/**
	 * Get the version string based on cache busting method.
	 *
	 * @param object $request Expected properties: basePath, width, version, outputExt.
	 * @param string|null $cacheVersion Optional override version.
	 */
	public function getCacheVersion(object $request, ?string $cacheVersion = null): string
	{
		if ($cacheVersion !== null) {
			return $cacheVersion;
		}
		$sourceFile = $this->getSourcePath($request->basePath);
		switch ($this->cacheBustingMethod) {
			case 'mtime':
				$cacheVersion = file_exists($sourceFile) ? (string)filemtime($sourceFile) : '0';
				break;
			case 'time':
				$cacheVersion = (string)time();
				break;
			case 'app':
			default:
				$cacheVersion = $this->appVersion;
		}
		return $cacheVersion;
	}

	/**
	 * Parse image request from URL path
	 * Matches: path/to/file-w600-v12345678.webp
	 */
	/**
	 * Parse image request from URL path.
	 *
	 * @return object|null Parsed object with properties: basePath, width, version, outputExt; or null if invalid.
	 */
	public function parseImageRequest(string $path): ?object
	{
		// Pattern: (.+)-w(\d+)-v(\d+)\.([a-z]+)
		if (preg_match('#^(.+)-w(\d+)-v(\d+)\.([a-z]+)$#i', $path, $m)) {
			$basePath = $m[1];
			return (object)[
				'basePath'    => $basePath,
				'width'       => (int)$m[2],
				'version'     => $m[3],
				'outputExt'   => $m[4],
			];
		}

		return null;
	}

	/**
	 * Get source file path
	 */
	/**
	 * Get source file path.
	 */
	public function getSourcePath(string $basePath): string
	{
		return rtrim($this->sourcePath, '/') . '/' . ltrim($basePath, '/');
	}

	/**
	 * Get cache file path (Matches the public filename exactly)
	 */
	/**
	 * Get cache file path (matches public filename exactly).
	 */
	public function getCachePath(string $basePath, int $width, string $outputExt, string $version = ''): string
	{
		$vPart = $version ? "-v{$version}" : "";
		return rtrim($this->cachePath, '/') . '/' . ltrim($basePath, '/') . "-w" . $width . $vPart . '.' . $outputExt;
	}

	/**
	 * Return container widths by breakpoint name.
	 *
	 * @return array<string,int>
	 */
	public function containers(): array
	{
		return $this->containers;
	}
	/**
	 * Return breakpoints by name.
	 *
	 * @return array<string,int>
	 */
	public function breakpoints(): array
	{
		return $this->breakpoints;
	}

	/**
	 * Return default render-time options.
	 */
	public function renderDefaults(): array
	{
		return [
			'file' => '',
			'outputExt' => $this->defaultOutputExt,
			'widths' => 'full',
			'gutter' => 0,
			'static' => false,
			'maxResolution' => $this->defaultMaxResolution,
			'resolutionStep' => $this->defaultResolutionStep,
			'maxWidth' => SuperImage::HIRES_SOURCE,
			'maxHeight' => SuperImage::HIRES_SOURCE,
			'loading' => $this->defaultLoading,
			'fetchPriority' => $this->defaultFetchPriority,
			'lqip' => $this->defaultLqip,
			'alt' => '',
			'cacheVersion' => null,
			'pictureAttr' => [],
			'imgAttr' => [],
			'prettyPrint' => $this->prettyPrint,
		];
	}
}
