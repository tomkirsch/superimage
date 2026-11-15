<?php

namespace Tomkirsch\SuperImage\Tests\Http;

use Tomkirsch\SuperImage\SuperImageWidths;

class TallRenderTest extends HttpTestCase
{
	public function testTallSourceEmitsOnlyDirectlyServableUrlsIncludingFallback(): void
	{
		$this->makeImage('tall.png', 1000, 20000, 'png');
		$this->startServer();
		$paths = [];
		foreach ([['widths' => SuperImageWidths::make()->full()],
			['widths' => 'full'], ['widths' => 'full', 'static' => true],
			['widths' => [1000]]] as $options) {
			$html = $this->superImage()->render(['src' => 'tall.png'] + $options);
			$this->assertStringContainsString('<img', $html);
			$this->assertStringNotContainsString('-w960-', $html);
			$this->assertStringNotContainsString('-w1000-', $html);
			preg_match_all('#/img/([^\s",]+-w(\d+)-v\d+\.webp)#', $html, $matches, PREG_SET_ORDER);
			$this->assertNotEmpty($matches);
			foreach ($matches as $match) {
				$this->assertLessThanOrEqual(800, (int)$match[2]);
				$paths[$match[1]] = (int)$match[2];
			}
		}
		$this->assertContains(800, $paths);
		foreach ($paths as $path => $width) {
			[$status, $body] = $this->get($path);
			$this->assertSame(200, $status, $path);
			$this->assertSame($width, getimagesizefromstring($body)[0]);
			$this->assertSame($width * 20, getimagesizefromstring($body)[1]);
		}
	}

	public function testNoValidCanonicalWidthUsesPlaceholderInsteadOfDeadFallback(): void
	{
		$this->makeImage('too-tall.png', 100, 17000, 'png');
		$html = $this->superImage()->render(['src' => 'too-tall.png', 'widths' => SuperImageWidths::make()->full()]);
		$this->assertStringContainsString('<svg', $html);
		$this->assertStringNotContainsString('/img/', $html);
		$this->assertSame('', $this->superImage()->imgUrl(100, ['src' => 'too-tall.png']));
	}
}
