<?php

namespace Tomkirsch\SuperImage\Tests\Unit;

use CodeIgniter\Exceptions\PageNotFoundException;
use Tomkirsch\SuperImage\Resizer;
use Tomkirsch\SuperImage\Tests\TestCase;

/** Resizer whose decode step is intercepted: proves rejection happens before any decode. */
class SpyResizer extends Resizer
{
	public int $decodes = 0;

	protected function doResize(object $request, string $sourcePath, string $cachePath): void
	{
		$this->decodes++;
		throw new \RuntimeException('decode reached');
	}
}

class PixelCapTest extends TestCase
{
	/** Header-only PNG (IHDR + IEND): getimagesize() reads dimensions, nothing could be decoded. */
	private function headerOnlyPng(string $rel, int $w, int $h): void
	{
		$chunk = fn(string $t, string $d) => pack('N', strlen($d)) . $t . $d . pack('N', crc32($t . $d));
		file_put_contents(
			$this->src . '/' . $rel,
			"\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0)) . $chunk('IEND', '')
		);
	}

	private function expect404(SpyResizer $r, string $rel, int $w): void
	{
		try {
			$r->serve($this->reqPath($rel, $w, 'png'));
			$this->fail('expected PageNotFoundException');
		} catch (PageNotFoundException) {
			$this->assertSame(0, $r->decodes);
		}
	}

	public function testHugeSourceRejectedBeforeDecode(): void
	{
		$this->config->maxSourcePixels = 10000000;
		$this->headerOnlyPng('big.png', 8000, 8000);
		$this->expect404(new SpyResizer($this->config), 'big.png', 400);
		$this->assertSame([], $this->cacheFiles());
	}

	public function testTooTallOutputRejected(): void
	{
		$this->headerOnlyPng('tall.png', 100, 17000);
		$this->expect404(new SpyResizer($this->config), 'tall.png', 100);
	}

	public function testOutputPixelCapRejected(): void
	{
		$this->config->maxSize = 0; // no width cap: 6000x5000 output = 30 MP > 25 MP
		$this->headerOnlyPng('wide.png', 6000, 5000);
		$this->expect404(new SpyResizer($this->config), 'wide.png', 6000);
	}

	public function testNormalImageReachesDecode(): void
	{
		$this->makeImage('ok.png', 1000, 800, 'png');
		$r = new SpyResizer($this->config);
		try {
			$r->serve($this->reqPath('ok.png', 400, 'png'));
			$this->fail('expected decode');
		} catch (\RuntimeException $e) {
			$this->assertSame(1, $r->decodes);
		}
	}

	public function testNoMemoryLimitGames(): void
	{
		$this->assertStringNotContainsString('memory_limit', file_get_contents(dirname(__DIR__, 2) . '/src/Resizer.php'));
	}
}
