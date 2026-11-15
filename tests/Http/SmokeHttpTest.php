<?php

namespace Tomkirsch\SuperImage\Tests\Http;

class SmokeHttpTest extends HttpTestCase
{
	public function testServesResizedJpeg(): void
	{
		$this->makeImage('a.jpg', 1200, 800);
		$this->startServer();
		[$status, $body, $h] = $this->get($this->reqPath('a.jpg', 400, 'jpg'));
		$this->assertSame(200, $status);
		$this->assertSame('image/jpeg', $h['content-type']);
		$this->assertSame(400, getimagesizefromstring($body)[0]);
	}
}
