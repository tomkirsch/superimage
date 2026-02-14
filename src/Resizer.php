<?php

namespace Tomkirsch\SuperImage;

use CodeIgniter\Images\Handlers\BaseHandler;

/**
 * Resizer - On-the-fly image resizing with caching
 */
class Resizer
{
	protected SuperImageConfig $config;
	protected ?BaseHandler $imageLib = null;
	protected ?bool $wasResized = null;

	public function __construct(?SuperImageConfig $config = null)
	{
		$this->config = $config ?? new SuperImageConfig();
	}

	/**
	 * Clean expired cache files
	 * 
	 * @return int Number of files deleted
	 */
	public function cleanExpired(): int
	{
		if ($this->config->cacheTTL <= 0) {
			return 0;
		}

		$count = 0;
		$expireTime = time() - $this->config->cacheTTL;

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator(
				$this->config->cachePath,
				\FilesystemIterator::SKIP_DOTS
			)
		);

		foreach ($iterator as $file) {
			if ($file->isFile() && $file->getMTime() < $expireTime) {
				@unlink($file->getRealPath());
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Clean all cache files for a specific image
	 * 
	 * @param string $basePath Image base path (e.g., 'products/photo')
	 * @return int Number of files deleted
	 */
	public function cleanImage(string $basePath): int
	{
		$count = 0;
		$pattern = $this->config->cachePath . $basePath . '-w*';

		foreach (glob($pattern) as $file) {
			@unlink($file);
			$count++;
		}

		return $count;
	}

	/**
	 * Clean entire cache directory
	 * 
	 * @return int Number of files deleted
	 */
	public function cleanAll(): int
	{
		$count = 0;

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator(
				$this->config->cachePath,
				\FilesystemIterator::SKIP_DOTS
			),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($iterator as $file) {
			if ($file->isFile()) {
				@unlink($file->getRealPath());
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Serve resized image
	 * @param string $requestPath URL path
	 */
	public function serve(string $requestPath): void
	{
		// Parse the request
		$request = $this->config->parseImageRequest($requestPath);
		if (!$request) {
			throw new \Exception("Invalid image request: {$requestPath}");
		}

		// check freshness
		$currentVersion = $this->config->getCacheVersion($request->basePath, $request->originalExt);
		if ($request->version !== $currentVersion) {
			// The user requested an old version. Redirect them to the current one.
			$newUrl = $this->config->imageUrl(
				$request->basePath,
				$request->originalExt,
				$request->outputExt,
				$request->width
			);
			// Grab the response, set the header, and exit
			response()->redirect($newUrl, 'auto', 301)->send();
			exit;
		}

		// Get source file
		$sourcePath = $this->config->getSourcePath($request->basePath, $request->originalExt);

		if (!file_exists($sourcePath)) {
			throw new \CodeIgniter\Exceptions\PageNotFoundException("Image not found: {$sourcePath}");
		}

		// Check cache 
		// Note: $request->version is used here to ensure the cache filename matches the URL
		$cachePath = $this->config->prepareCachePath($request);

		// We check if it exists. If it does, we serve it. 
		// The .htaccess handles the "fast" check; PHP handles the "fallback" check.
		if (!file_exists($cachePath)) {
			$this->resize($request, $sourcePath, $cachePath);
			$this->wasResized = true;
		} else {
			$this->wasResized = false;
		}

		// Output the image
		$this->output($cachePath, $request->outputExt);
	}

	/**
	 * Resize image and save to cache with locking
	 * @param object $request Parsed request object
	 * @param string $sourcePath Path to source image
	 * @param string $cachePath Path to save resized image
	 */
	protected function resize(object $request, string $sourcePath, string $cachePath): void
	{
		$dir = dirname($cachePath);
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}

		$lockFile = $cachePath . '.lock';
		$fp = fopen($lockFile, 'w+');

		if (flock($fp, LOCK_EX)) {
			// Double-check existence inside lock to prevent redundant resizes
			if (!file_exists($cachePath)) {
				$this->doResize($request, $sourcePath, $cachePath);
			}
			flock($fp, LOCK_UN);
		}
		fclose($fp);
		@unlink($lockFile);
	}

	protected function doResize(object $request, string $sourcePath, string $cachePath): void
	{
		ini_set('memory_limit', '256M');

		// Before we save the new one, find and kill old versioned files for THIS image/width
		// Matches: path/to/image-w600-v*.webp
		$oldVersionsPattern = $this->config->cachePath . $request->basePath . "-w" . $request->width . "-v*." . $request->outputExt;
		foreach (glob($oldVersionsPattern) as $oldFile) {
			@unlink($oldFile);
		}

		if (!$this->imageLib) {
			$this->imageLib = \Config\Services::image('gd');
		}

		$this->imageLib->withFile($sourcePath);

		$sourceWidth = $this->imageLib->getWidth();
		$sourceHeight = $this->imageLib->getHeight();
		$ratio = $sourceWidth / $sourceHeight;

		$width = $request->width;

		// Scale logic
		if (!$this->config->allowUpscale && $width > $sourceWidth) {
			$width = $sourceWidth;
		}

		$height = (int)round($width / $ratio);

		if ($this->config->maxSize > 0) {
			if ($width > $this->config->maxSize) {
				$width = $this->config->maxSize;
				$height = (int)round($width / $ratio);
			}
		}

		$this->imageLib->resize($width, $height)
			->save($cachePath);
	}

	protected function output(string $filePath, string $ext): void
	{
		while (ob_get_level()) {
			ob_end_clean();
		}

		$mime = $this->getMimeType($ext);
		header("Content-Type: {$mime}");
		header("Content-Length: " . filesize($filePath));
		header("Last-Modified: " . gmdate('D, d M Y H:i:s T', filemtime($filePath)));

		// With versioned URLs, we can set aggressive caching
		header("Cache-Control: public, max-age=31536000, immutable");

		// send debug header in development
		if (env('CI_ENVIRONMENT') === 'development') {
			header("X-Superimage-Cache: " . ($this->wasResized ? 'write' : 'php'));
			$sourceFile = $this->config->getSourcePath(
				$this->config->parseImageRequest(basename($filePath))->basePath,
				$this->config->parseImageRequest(basename($filePath))->originalExt
			);
			header("X-Superimage-Source: " . $sourceFile);
		}

		readfile($filePath);
	}

	protected function getMimeType(string $ext): string
	{
		return match (strtolower($ext)) {
			'jpg', 'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'webp' => 'image/webp',
			'avif' => 'image/avif',
			default => 'application/octet-stream'
		};
	}
}
