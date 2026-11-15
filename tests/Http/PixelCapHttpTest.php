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

	public function testWideImageIs404AndLeavesNoFile(): void
	{
		$im = imagecreatetruecolor(17000, 100);
		imagejpeg($im, $this->src . '/wide.jpg');
		$this->config->maxSize = 0;
		$this->startServer(1, ['SI_MAX_SIZE' => '0']);
		[$s] = $this->get($this->reqPath('wide.jpg', 17000, 'webp'));
		$this->assertSame(404, $s);
		$this->assertSame([], $this->cacheFiles());
	}
}
