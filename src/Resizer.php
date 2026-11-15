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
			throw new \CodeIgniter\Exceptions\PageNotFoundException('Image not found');
		}

		// Lexical check: no empty/./.. segments, absolute paths, NUL, control chars or backslashes
		$base = $request->basePath;
		if (preg_match('#[\x00-\x1f\x7f\\\\]#', $base) || $base[0] === '/') {
			throw new \CodeIgniter\Exceptions\PageNotFoundException('Image not found');
		}
		foreach (explode('/', $base) as $segment) {
			if ($segment === '' || $segment === '.' || $segment === '..') {
				throw new \CodeIgniter\Exceptions\PageNotFoundException('Image not found');
			}
		}

		// Containment: the real source file must live under sourcePath (before any other file access)
		$sourcePath = $this->config->getSourcePath($base);
		$root = realpath($this->config->sourcePath);
		$real = $root === false ? false : realpath($sourcePath);
		if ($real === false || !is_file($real) || strpos($real, rtrim($root, '/') . '/') !== 0) {
			throw new \CodeIgniter\Exceptions\PageNotFoundException('Image not found');
		}

		$size = @getimagesize($real);
		if (!$size || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > $this->config->maxSourcePixels) {
			throw new \CodeIgniter\Exceptions\PageNotFoundException('Image not found');
		}

		// check width ladder and freshness
		$snapped = $this->config->snapWidth($request->width, $size[0]);

		// Output caps: libgd/JPEG/WebP limit height to 16383; keep the output bitmap bounded
		$outHeight = (int)round($snapped * $size[1] / $size[0]);
		if ($outHeight < 1 || $outHeight > 16383 || $snapped * $outHeight > 25000000) {
			throw new \CodeIgniter\Exceptions\PageNotFoundException('Image not found');
		}
		$currentVersion = $this->config->getCacheVersion($request);
		if ($snapped !== $request->width || $request->version !== $currentVersion) {
			// Off-ladder width or old version: redirect to the canonical URL (nothing is written)
			$newUrl = $this->config->imageUrl(
				$request->basePath,
				$snapped,
				$currentVersion,
				$request->outputExt
			);
			// Grab the response, set the header, and exit
			response()->redirect($newUrl, 'auto', $snapped !== $request->width ? 302 : 301)->send();
			exit;
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
		$this->output($cachePath);
	}

	/**
	 * Resize image and save to cache (atomic, lock-free: concurrent requests may both render, last rename wins)
	 * @param object $request Parsed request object
	 * @param string $sourcePath Path to source image
	 * @param string $cachePath Path to save resized image
	 */
	protected function resize(object $request, string $sourcePath, string $cachePath): void
	{
		$dir = dirname($cachePath);
		if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
			throw new \RuntimeException('Cannot create cache directory');
		}

		$this->doResize($request, $sourcePath, $cachePath);
	}

	protected function doResize(object $request, string $sourcePath, string $cachePath): void
	{
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

		// convert() must precede save() so the bytes match the extension
		$type = ['webp' => IMAGETYPE_WEBP, 'png' => IMAGETYPE_PNG][$request->outputExt] ?? IMAGETYPE_JPEG;
		$this->imageLib->resize($width, $height)
			->convert($type);

		// write to a temp name, then rename into place so readers never see a partial file
		$tmp = $cachePath . '.' . bin2hex(random_bytes(6)) . '.tmp';
		try {
			$this->imageLib->save($tmp);
			if (!rename($tmp, $cachePath)) {
				throw new \RuntimeException('Cannot write cache file');
			}
		} finally {
			@unlink($tmp);
		}

		// file saving hook
		$this->config->onFileSave($cachePath, $request);
	}

	protected function output(string $filePath, ?string $mimeType = null): void
	{
		while (ob_get_level()) {
			ob_end_clean();
		}
		if (empty($mimeType)) {
			$ext = pathinfo($filePath, PATHINFO_EXTENSION);
			$mimeType = $this->getMimeType($ext);
		}
		header("Content-Type: {$mimeType}");
		header("Content-Length: " . filesize($filePath));
		header("Last-Modified: " . gmdate('D, d M Y H:i:s T', filemtime($filePath)));

		// With versioned URLs, we can set aggressive caching
		header("Cache-Control: public, max-age=31536000, immutable");

		// send debug header in development
		if (env('CI_ENVIRONMENT') === 'development') {
			header("X-Superimage-Cache: " . ($this->wasResized ? 'write' : 'php'));
			$sourceFile = $this->config->getSourcePath(
				$this->config->parseImageRequest(basename($filePath))->basePath
			);
			header("X-Superimage-Source: " . $sourceFile);
			header("X-Superimage-Cache-File: " . $filePath);
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
