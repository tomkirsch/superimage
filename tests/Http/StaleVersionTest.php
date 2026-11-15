<?php

namespace Tomkirsch\SuperImage\Tests\Http;

class StaleVersionTest extends HttpTestCase
{
	public function testStaleVersionIs302AndCurrentTargetServesDirectly(): void
	{
		$this->makeImage('a.jpg', 1200, 800);
		$this->startServer();
		[$status, , $headers] = $this->get('a.jpg-w400-v1.webp');
		$this->assertSame(302, $status);
		$this->assertStringEndsWith('/img/' . $this->reqPath('a.jpg', 400), $headers['location']);
		$this->assertSame([], $this->cacheFiles());
		[$status, $body] = $this->get(substr($headers['location'], strpos($headers['location'], '/img/') + 5));
		$this->assertSame(200, $status);
		$this->assertSame(400, getimagesizefromstring($body)[0]);
	}
}
