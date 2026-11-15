<?php

namespace Tomkirsch\SuperImage\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Tomkirsch\SuperImage\Resizer;
use Tomkirsch\SuperImage\SuperImage;
use Tomkirsch\SuperImage\SuperImageConfig;

/**
 * Each test gets a private sandbox under tests/_tmp/ (inside this checkout, git-ignored)
 * holding a source dir, a cache dir and a sibling "outside" dir for containment tests.
 */
abstract class TestCase extends BaseTestCase
{
	protected string $sandbox;
	protected string $src;
	protected string $cache;
	protected string $outside;
	protected SuperImageConfig $config;

	protected function setUp(): void
	{
		parent::setUp();
		$this->sandbox = dirname(__DIR__) . '/tests/_tmp/' . bin2hex(random_bytes(6));
		$this->src = $this->sandbox . '/src';
		$this->cache = $this->sandbox . '/cache';
		$this->outside = $this->sandbox . '/outside';
		foreach ([$this->src, $this->cache, $this->outside] as $d) {
			mkdir($d, 0777, true);
		}
		$this->config = $this->makeConfig();
	}

	protected function tearDown(): void
	{
		$this->rrmdir($this->sandbox);
		parent::tearDown();
	}

	protected function makeConfig(array $props = []): SuperImageConfig
	{
		$cfg = new SuperImageConfig();
		$cfg->sourcePath = $this->src . '/';
		$cfg->cachePath = $this->cache . '/';
		foreach ($props as $k => $v) {
			$cfg->$k = $v;
		}
		return $cfg;
	}

	protected function superImage(?SuperImageConfig $cfg = null): SuperImage
	{
		return new SuperImage($cfg ?? $this->config);
	}

	protected function resizer(?SuperImageConfig $cfg = null): Resizer
	{
		return new Resizer($cfg ?? $this->config);
	}

	/** Create a real image file of the given type ('jpeg'|'png'|'webp'|'gif'). Returns the path. */
	protected function makeImage(string $relPath, int $w, int $h, string $type = 'jpeg', ?string $baseDir = null): string
	{
		$path = ($baseDir ?? $this->src) . '/' . $relPath;
		if (!is_dir(dirname($path))) {
			mkdir(dirname($path), 0777, true);
		}
		$im = imagecreatetruecolor($w, $h);
		imagefill($im, 0, 0, imagecolorallocate($im, 200, 50, 50));
		// a marker in the top-left so orientation can be detected: blue block
		imagefilledrectangle($im, 0, 0, max(0, intdiv($w, 4) - 1), max(0, intdiv($h, 4) - 1), imagecolorallocate($im, 0, 0, 255));
		match ($type) {
			'png' => imagepng($im, $path),
			'webp' => imagewebp($im, $path),
			'gif' => imagegif($im, $path),
			default => imagejpeg($im, $path, 95),
		};
		return $path;
	}

	/** JPEG whose stored pixels are $w x $h with an EXIF Orientation tag injected (APP1). */
	protected function makeExifJpeg(string $relPath, int $w, int $h, int $orientation): string
	{
		$path = $this->makeImage($relPath, $w, $h, 'jpeg');
		$jpeg = file_get_contents($path);
		// big-endian TIFF: header, IFD0 with one entry (0x0112 Orientation, SHORT)
		$tiff = "MM\x00\x2A\x00\x00\x00\x08" . "\x00\x01" . "\x01\x12\x00\x03\x00\x00\x00\x01" . pack('n', $orientation) . "\x00\x00" . "\x00\x00\x00\x00";
		$payload = "Exif\x00\x00" . $tiff;
		$app1 = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;
		file_put_contents($path, substr($jpeg, 0, 2) . $app1 . substr($jpeg, 2));
		return $path;
	}

	/** Dominant detail: returns [r,g,b] at pixel (x,y) of an image file. */
	protected function pixel(string $file, int $x, int $y): array
	{
		$im = imagecreatefromstring(file_get_contents($file));
		$c = imagecolorat($im, $x, $y);
		return [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF];
	}

	protected function isBlue(array $rgb): bool
	{
		return $rgb[2] > 200 && $rgb[0] < 80;
	}

	protected function version(string $rel): string
	{
		return (string)filemtime($this->src . '/' . $rel);
	}

	/** Public request path for a variant, using the current mtime version. */
	protected function reqPath(string $rel, int $w, string $ext = 'webp'): string
	{
		return "{$rel}-w{$w}-v" . $this->version($rel) . ".{$ext}";
	}

	/** @return list<string> all files under cache (relative), sorted */
	protected function cacheFiles(): array
	{
		$out = [];
		if (!is_dir($this->cache)) {
			return $out;
		}
		$it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->cache, \FilesystemIterator::SKIP_DOTS));
		foreach ($it as $f) {
			if ($f->isFile()) {
				$out[] = substr($f->getPathname(), strlen($this->cache) + 1);
			}
		}
		sort($out);
		return $out;
	}

	protected function rrmdir(string $dir): void
	{
		if (!is_dir($dir) || is_link($dir)) {
			return;
		}
		foreach (scandir($dir) as $e) {
			if ($e === '.' || $e === '..') {
				continue;
			}
			$p = $dir . '/' . $e;
			is_dir($p) && !is_link($p) ? $this->rrmdir($p) : @unlink($p);
		}
		@rmdir($dir);
	}
}
