<?php

namespace Tomkirsch\SuperImage\Tests\Http;

class LadderTest extends HttpTestCase
{
	public function testEveryWidthYieldsBoundedFiles(): void
	{
		$this->makeImage('a.jpg', 1000, 600);
		$this->startServer(4);
		$ladder = $this->config->widthLadder;
		$expectedMax = count(array_filter($ladder, fn($w) => $w < 1000)) + 1;
		$codes = [];
		for ($w = 1; $w <= 1100; $w += 7) {
			[$s, , $h] = $this->get($this->reqPath('a.jpg', $w));
			$codes[$s] = ($codes[$s] ?? 0) + 1;
			if ($s === 302) {
				$this->assertMatchesRegularExpression('#-w(\d+)-v#', $h['location']);
			}
		}
		[$s] = $this->get($this->reqPath('a.jpg', 5000));
		$this->assertSame(302, $s);
		$this->assertArrayHasKey(200, $codes);
		$this->assertArrayHasKey(302, $codes);
		// follow every redirect so files get written
		for ($w = 1; $w <= 1100; $w += 7) {
			[$s, , $h] = $this->get($this->reqPath('a.jpg', $w));
			if ($s === 302) {
				$path = substr($h['location'], strpos($h['location'], '/img/') + 5);
				[$s] = $this->get($path);
			}
			$this->assertSame(200, $s);
		}
		$files = $this->cacheFiles();
		$this->assertLessThanOrEqual($expectedMax, count($files));
		$this->assertContains('a.jpg-w1000-v' . $this->version('a.jpg') . '.webp', $files);
	}

	public function testOffLadderRedirectsWithoutWriting(): void
	{
		$this->makeImage('a.jpg', 1000, 600);
		$this->startServer();
		[$s, , $h] = $this->get($this->reqPath('a.jpg', 401));
		$this->assertSame(302, $s);
		$this->assertStringContainsString('-w480-v', $h['location']);
		$this->assertSame([], $this->cacheFiles());
	}

	/** Real round trip: every URL render() emits is served 200 directly (no redirect). */
	public function testRenderedUrlsAreServedWithoutRedirect(): void
	{
		$this->makeImage('a.jpg', 1500, 900);
		$this->startServer(4);
		$optsList = [
			['widths' => 'half', 'gutter' => 15],
			['widths' => \Tomkirsch\SuperImage\SuperImageWidths::make()->full()->at(800, 'half')],
			['widths' => [0 => 333, 768 => 517], 'static' => true],
			['widths' => [100, 200, 300]],
			['widths' => [150]],
		];
		$n = 0;
		foreach ($optsList as $opts) {
			$html = $this->superImage()->render(['src' => 'a.jpg'] + $opts);
			preg_match_all('#https?://[^\s",]+/img/([^\s",]+-w\d+-v\d+\.\w+)#', $html, $m);
			foreach (array_unique($m[1]) as $path) {
				[$s] = $this->get($path);
				$this->assertSame(200, $s, $path);
				$n++;
			}
		}
		$this->assertGreaterThan(5, $n);
	}

	public function testUpscaleWithoutMaxSizeServesLadderWidthAboveSource(): void
	{
		$this->makeImage('s.jpg', 80, 40);
		$this->startServer(1, ['SI_MAX_SIZE' => '0', 'SI_UPSCALE' => '1']);
		[$s, $body] = $this->get($this->reqPath('s.jpg', 480));
		$this->assertSame(200, $s);
		$this->assertSame(480, getimagesizefromstring($body)[0]);
		[$s] = $this->get($this->reqPath('s.jpg', 99999));
		$this->assertContains($s, [302, 404]);
		$this->assertCount(1, $this->cacheFiles());
	}

	public function testVeryThinSourceRendersAndServes(): void
	{
		$im = imagecreatetruecolor(10000, 1);
		imagejpeg($im, $this->src . '/thin.jpg');
		$this->startServer(2);
		$html = $this->superImage()->render(['src' => 'thin.jpg', 'widths' => 'half']);
		preg_match_all('#/img/([^\s",]+-w\d+-v\d+\.\w+)#', $html, $m);
		$this->assertNotEmpty($m[1]);
		foreach (array_unique($m[1]) as $path) {
			[$s, $body] = $this->get($path);
			$this->assertSame(200, $s, $path);
			$this->assertGreaterThanOrEqual(1, getimagesizefromstring($body)[1]);
		}
	}
}
