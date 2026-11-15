<?php

namespace Tomkirsch\SuperImage\Tests\Http;

class ValidationTest extends HttpTestCase
{
	public function testBadInputIs404NotFound(): void
	{
		$this->makeImage('a.jpg', 1200, 800);
		$this->makeImage('secret.jpg', 1200, 800, 'jpeg', $this->outside);
		symlink($this->outside . '/secret.jpg', $this->src . '/link.jpg');
		symlink($this->outside, $this->src . '/linkdir');
		$v = $this->version('a.jpg');
		$this->startServer();
		$paths = [
			'garbage',
			'a.jpg-w0-v' . $v . '.webp',
			'a.jpg-w01-v' . $v . '.webp',
			'a.jpg-w123456-v' . $v . '.webp',
			'a.jpg-w400-v.webp',
			'a.jpg-w400-v' . $v . '.gif',
			'../outside/secret.jpg-w400-v1.webp',
			'%2e%2e/outside/secret.jpg-w400-v1.webp',
			'sub/../a.jpg-w400-v' . $v . '.webp',
			'./a.jpg-w400-v' . $v . '.webp',
			'sub//a.jpg-w400-v' . $v . '.webp',
			'/a.jpg-w400-v' . $v . '.webp',
			'%2fetc/passwd-w400-v1.webp',
			'a.jpg%00x-w400-v' . $v . '.webp',
			'sub%5Ca.jpg-w400-v1.webp',
			'link.jpg-w400-v' . $v . '.webp',
			'linkdir/secret.jpg-w400-v1.webp',
			'missing.jpg-w400-v1.webp',
		];
		foreach ($paths as $p) {
			[$s] = $this->get($p);
			$this->assertSame(404, $s, $p);
		}
		$this->assertSame([], $this->cacheFiles());
		$this->assertSame(['secret.jpg'], array_values(array_diff(scandir($this->outside), ['.', '..'])));
	}

	public function testValidRequestStillWorksAndSubdirsAndUppercaseExt(): void
	{
		$this->makeImage('sub/a.jpg', 1200, 800);
		$this->startServer();
		[$s] = $this->get($this->reqPath('sub/a.jpg', 400));
		$this->assertSame(200, $s);
		[$s, , $h] = $this->get($this->reqPath('sub/a.jpg', 400, 'JPG'));
		$this->assertSame(200, $s);
		$this->assertSame('image/jpeg', $h['content-type']);
	}
}
