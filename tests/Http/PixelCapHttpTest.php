<?php

namespace Tomkirsch\SuperImage\Tests\Http;

class PixelCapHttpTest extends HttpTestCase
{
	public function testTallImageIs404Not500(): void
	{
		$im = imagecreatetruecolor(100, 17000);
		imagepng($im, $this->src . '/tall.png');
		$this->startServer();
		[$s] = $this->get($this->reqPath('tall.png', 100, 'png'));
		$this->assertSame(404, $s);
		$this->assertSame([], $this->cacheFiles());
	}
}
