<?php

namespace Tomkirsch\SuperImage\Tests\Http;

class AtomicWriteTest extends HttpTestCase
{
	private function leftovers(): array
	{
		return array_values(array_filter($this->cacheFiles(), fn($f) => preg_match('/\.(lock|tmp)$/', $f)));
	}

	public function testNoLockOrTmpAfterSuccessOrFailure(): void
	{
		$this->makeImage('a.jpg', 1200, 800);
		file_put_contents($this->src . '/bad.jpg', 'not an image at all');
		$this->startServer();
		[$s] = $this->get($this->reqPath('a.jpg', 400));
		$this->assertSame(200, $s);
		$this->assertSame([], $this->leftovers());
		[$s] = $this->get($this->reqPath('bad.jpg', 400));
		$this->assertNotSame(200, $s);
		$this->assertSame([], $this->leftovers());
		$this->assertSame(['a.jpg-w400-v' . $this->version('a.jpg') . '.webp'], $this->cacheFiles());
	}

	public function testParallelColdRequestsAreIdenticalAndDecodable(): void
	{
		$this->makeImage('a.jpg', 1600, 1000);
		$this->startServer(8);
		$url = 'http://127.0.0.1:' . $this->port . '/img/' . $this->reqPath('a.jpg', 800);
		$mh = curl_multi_init();
		$hs = [];
		for ($i = 0; $i < 16; $i++) {
			$h = curl_init($url);
			curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
			curl_multi_add_handle($mh, $h);
			$hs[] = $h;
		}
		do {
			curl_multi_exec($mh, $running);
			curl_multi_select($mh, 0.2);
		} while ($running);
		$bodies = [];
		foreach ($hs as $h) {
			$this->assertSame(200, curl_getinfo($h, CURLINFO_HTTP_CODE));
			$bodies[] = curl_multi_getcontent($h);
		}
		$this->assertCount(1, array_unique($bodies), 'bodies must be byte-identical');
		$this->assertSame(800, getimagesizefromstring($bodies[0])[0]);
		$this->assertNotFalse(imagecreatefromstring($bodies[0]));
		$this->assertSame([], $this->leftovers());
	}
}
