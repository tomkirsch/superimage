<?php

namespace Tomkirsch\SuperImage\Tests\Http;

use Tomkirsch\SuperImage\SuperImageWidths;

class LegacyBuilderTest extends HttpTestCase
{
	public function testResolveOverridePreservesPixelSizesAndBoundedCandidateUrls(): void
	{
		$this->makeImage('image.png', 2400, 1600, 'png');
		$this->startServer();
		$builder = new class extends SuperImageWidths {
			public function resolve(int $gutter = 0): array { return [0 => 300 - $gutter]; }
		};
		$builder->full(); // overriding resolve remains authoritative even with fluent state
		foreach ([1 => [320], 2 => [320, 480, 640]] as $maxResolution => $expected) {
			$html = $this->superImage()->render(['src' => 'image.png', 'widths' => $builder,
				'maxResolution' => $maxResolution]);
			$this->assertStringContainsString('sizes="300px"', $html);
			$this->assertStringNotContainsString('100vw', $html);
			preg_match('/srcset="([^"]+)"/', $html, $srcset);
			preg_match_all('#/img/([^\s",]+-w(\d+)-v\d+\.webp)#', $srcset[1], $matches, PREG_SET_ORDER);
			$this->assertSame($expected, array_map(fn($m) => (int)$m[2], $matches));
			foreach ($matches as $match) {
				[$status, $body] = $this->get($match[1]);
				$this->assertSame(200, $status);
				$this->assertSame((int)$match[2], getimagesizefromstring($body)[0]);
			}
		}
		$html = $this->superImage()->render(['src' => 'image.png', 'widths' => $builder, 'gutter' => 20]);
		$this->assertStringContainsString('sizes="280px"', $html);
	}

	public function testCustomPixelMapSizesAreOrderedAndInheritedBuilderRemainsFluid(): void
	{
		$builder = new class extends SuperImageWidths {
			public function resolve(int $gutter = 0): array { return [0 => 300 - $gutter, 1024 => 600 - $gutter]; }
		};
		$this->assertSame('(min-width: 1024px) 580px, 280px', $builder->sizes(20));
		$inherited = new class extends SuperImageWidths {};
		$inherited->full()->half(800);
		$this->assertTrue($inherited->hasFluidSlots());
		$this->assertSame('(min-width: 800px) 50vw, 100vw', $inherited->sizes());
	}
}
