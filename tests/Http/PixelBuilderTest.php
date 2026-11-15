<?php

namespace Tomkirsch\SuperImage\Tests\Http;

use Tomkirsch\SuperImage\SuperImageWidths;

class PixelBuilderTest extends HttpTestCase
{
	public function testAllPixelSlotsHonorDensityLimitAndCandidatesRoundtrip(): void
	{
		$this->makeImage('image.png', 2400, 1600, 'png');
		$this->startServer();
		foreach ([1 => [160], 2 => [160, 240, 320], 3 => [160, 240, 320, 400, 480]] as $maxResolution => $expected) {
			$html = $this->superImage()->render(['src' => 'image.png',
				'widths' => SuperImageWidths::make()->px(0, 150), 'maxResolution' => $maxResolution]);
			$this->assertStringContainsString('sizes="150px"', $html);
			preg_match('/srcset="([^"]+)"/', $html, $srcset);
			preg_match_all('#/img/([^\s",]+-w(\d+)-v\d+\.webp)#', $srcset[1], $matches, PREG_SET_ORDER);
			$this->assertSame($expected, array_map(fn($m) => (int)$m[2], $matches));
			foreach ($matches as $match) {
				[$status, $body] = $this->get($match[1]);
				$this->assertSame(200, $status);
				$this->assertSame((int)$match[2], getimagesizefromstring($body)[0]);
			}
		}
	}

	public function testMixedFluidSlotsKeepViewportCoverageAsDocumented(): void
	{
		$this->makeImage('image.png', 2400, 1600, 'png');
		$builder = SuperImageWidths::make()->px(0, 150)->half(800);
		$html = $this->superImage()->render(['src' => 'image.png', 'widths' => $builder, 'maxResolution' => 1]);
		$this->assertStringContainsString('sizes="(min-width: 800px) 50vw, 150px"', $html);
		$this->assertStringContainsString(' 2400w', $html);
		$this->assertTrue(SuperImageWidths::make()->px(800, 150)->hasFluidSlots()); // implicit mobile full
	}
}
