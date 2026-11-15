<?php

namespace Tomkirsch\SuperImage\Tests\Http;

class EncodingTest extends HttpTestCase
{
	public function testBytesMatchExtension(): void
	{
		$this->makeImage('a.jpg', 1200, 800, 'jpeg');
		$this->makeImage('b.png', 1200, 800, 'png');
		$this->startServer();
		[$s, $body] = $this->get($this->reqPath('a.jpg', 400, 'webp'));
		$this->assertSame(200, $s);
		$this->assertSame('RIFF', substr($body, 0, 4));
		$this->assertSame('WEBP', substr($body, 8, 4));
		[$s, $body] = $this->get($this->reqPath('b.png', 400, 'jpg'));
		$this->assertSame("\xFF\xD8", substr($body, 0, 2));
		[$s, $body] = $this->get($this->reqPath('a.jpg', 400, 'png'));
		$this->assertSame("\x89PNG", substr($body, 0, 4));
		[$s, $body] = $this->get($this->reqPath('a.jpg', 400, 'JPEG'));
		$this->assertSame("\xFF\xD8", substr($body, 0, 2));
	}

	public function testDisallowedExtensionIs404AndWritesNothing(): void
	{
		$this->makeImage('a.jpg', 1200, 800);
		$this->startServer();
		foreach (['php', 'html', 'avif', 'gif', 'phtml'] as $ext) {
			[$s] = $this->get($this->reqPath('a.jpg', 400, $ext));
			$this->assertSame(404, $s, $ext);
		}
		$this->assertSame([], $this->cacheFiles());
	}
}
