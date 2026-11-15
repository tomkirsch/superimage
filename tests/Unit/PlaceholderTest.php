<?php

namespace Tomkirsch\SuperImage\Tests\Unit;

use Tomkirsch\SuperImage\Tests\TestCase;

class PlaceholderTest extends TestCase
{
	public function testProductionHidesPathButLogsItAndDevelopmentShowsIt(): void
	{
		foreach (['production', 'development'] as $environment) {
			$command = escapeshellarg(PHP_BINARY) . ' tests/placeholder-process.php '
				. escapeshellarg($environment) . ' ' . escapeshellarg($this->src);
			$output = [];
			exec($command, $output, $status);
			$this->assertSame(0, $status);
			$result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
			$this->assertStringContainsString('<svg', $result['html']);
			if ($environment === 'production') {
				$this->assertStringNotContainsString($this->src, $result['html']);
				$this->assertStringNotContainsString('missing.jpg', $result['html']);
				$this->assertStringContainsString('Image unavailable', $result['html']);
			} else {
				$this->assertStringContainsString($this->src . '/missing.jpg', $result['html']);
			}
			$this->assertSame('error', $result['logs'][0][0]);
			$this->assertStringContainsString($this->src . '/missing.jpg', $result['logs'][0][1]);
		}
	}
}
