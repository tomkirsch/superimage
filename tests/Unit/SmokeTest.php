<?php

namespace Tomkirsch\SuperImage\Tests\Unit;

use Tomkirsch\SuperImage\Tests\TestCase;

class SmokeTest extends TestCase
{
	public function testRenderProducesPictureWithVersionedUrl(): void
	{
		$this->makeImage('a.jpg', 1200, 800);
		$html = $this->superImage()->render(['src' => 'a.jpg', 'alt' => 'x']);
		$this->assertStringContainsString('<picture', $html);
		$this->assertMatchesRegularExpression('#a\.jpg-w\d+-v' . $this->version('a.jpg') . '\.webp#', $html);
	}
}
