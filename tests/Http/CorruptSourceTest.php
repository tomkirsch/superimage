<?php

namespace Tomkirsch\SuperImage\Tests\Http;

class CorruptSourceTest extends HttpTestCase
{
	public function testTruncatedPngIs404AndLeavesNothing(): void
	{
		$this->makeImage('full.png', 1000, 600, 'png');
		$png = file_get_contents($this->src . '/full.png');
		file_put_contents($this->src . '/trunc.png', substr($png, 0, 60)); // valid header, no pixel data
		$this->assertNotFalse(getimagesize($this->src . '/trunc.png'));
		$this->startServer();
		foreach (['png', 'webp', 'jpg'] as $ext) {
			[$s] = $this->get($this->reqPath('trunc.png', 400, $ext));
			$this->assertSame(404, $s, $ext);
		}
		$this->assertSame([], $this->cacheFiles());
	}
}
