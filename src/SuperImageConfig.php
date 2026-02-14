<?php

namespace Tomkirsch\SuperImage;

use CodeIgniter\Config\BaseConfig;

class SuperImageConfig extends BaseConfig
{
	public bool $prettyPrint = false;
	public string $defaultOutputExt = 'webp';

	public array $containers = [
		'xxl' => 1320,
		'xl' => 1140,
		'lg' => 960,
		'md' => 720,
		'sm' => 540,
	];

	public array $breakpoints = [
		'xxl' => 1400,
		'xl' => 1200,
		'lg' => 992,
		'md' => 768,
		'sm' => 576,
	];

	public float $defaultMaxResolution = 2.0;
	public float $defaultResolutionStep = 0.5;
	public string $defaultLoading = 'auto';
	public string $defaultFetchPriority = 'auto';
	public ?string $defaultLqip = null;

	// =========================================================================
	// FILE PATHS
	// =========================================================================

	public string $sourcePath = WRITEPATH . 'img/';
	public string $cachePath = FCPATH . '_superimage_cache/';
	public string $publicUrlPrefix = 'img/';

	// =========================================================================
	// SERVING STRATEGY
	// =========================================================================

	public string $servingStrategy = 'htaccess';

	/**
	 * Now forced to true for the filename-based versioning logic.
	 */
	public bool $enableCacheBusting = true;

	public string $cacheBustingMethod = 'mtime';
	public string $appVersion = '1';

	// =========================================================================
	// RESIZING CONSTRAINTS
	// =========================================================================

	public bool $allowUpscale = false;
	public int $maxSize = 3000;
	public int $cacheTTL = 0; 

    // =========================================================================
    // METHODS
    // =========================================================================

	/**
	 * Generate the public-facing image URL with versioning in the filename
	 * Pattern: path/to/file-w600-v12345678.webp
	 */
	public function imageUrlGenerator(string $filePath, string $originalExt, string $outputExt, int $width): string
	{
		$version = $this->getCacheVersion($filePath, $originalExt);

		// Construct filename: filename-w{width}-v{version}.{ext}
		$filename = "{$filePath}-w{$width}-v{$version}.{$outputExt}";

		return base_url("{$this->publicUrlPrefix}{$filename}");
	}

	/**
	 * Prepares the cache path and cleans up old versions of THIS specific image/width.
	 * Called by Resizer before saving a new image.
	 */
	public function prepareCachePath(object $request): string
	{
		// 1. Build the path for the CURRENT version
		// path/to/image-w600-v134342433.webp
		$newCachePath = $this->cachePath . $request->basePath . "-w" . $request->width . "-v" . $request->version . "." . $request->outputExt;

		// 2. Targeted Cleanup
		// Look for any other -v* files for this specific image and width
		$pattern = $this->cachePath . $request->basePath . "-w" . $request->width . "-v*." . $request->outputExt;

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
	public function getCacheVersion(string $filePath, string $originalExt): string
	{
		switch ($this->cacheBustingMethod) {
			case 'mtime':
				$sourceFile = $this->getSourcePath($filePath, $originalExt);
				return file_exists($sourceFile) ? (string)filemtime($sourceFile) : $this->appVersion;
			case 'time':
				return (string)time();
			case 'app':
			default:
				return $this->appVersion;
		}
	}

	/**
	 * Parse image request from URL path
	 * Matches: path/to/file-w600-v12345678.webp
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
				'originalExt' => $this->detectOriginalExt($basePath)
			];
		}

		return null;
	}

	/**
	 * Get source file path
	 */
	public function getSourcePath(string $basePath, string $ext): string
	{
		return rtrim($this->sourcePath, '/') . '/' . ltrim($basePath, '/') . '.' . $ext;
	}

	/**
	 * Get cache file path (Matches the public filename exactly)
	 */
	public function getCachePath(string $basePath, int $width, string $outputExt, string $version = ''): string
	{
		$vPart = $version ? "-v{$version}" : "";
		return $this->cachePath . $basePath . "-w" . $width . $vPart . '.' . $outputExt;
	}

	/**
	 * Detect original file extension
	 */
	protected function detectOriginalExt(string $basePath): string
	{
		$fullPathNoExt = rtrim($this->sourcePath, '/') . '/' . ltrim($basePath, '/');

		foreach (['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'] as $ext) {
			if (file_exists($fullPathNoExt . '.' . $ext)) {
				return $ext;
			}
		}
		return 'jpg';
	}

	public function containers(): array
	{
		return $this->containers;
	}
	public function breakpoints(): array
	{
		return $this->breakpoints;
	}
}
