<?php

namespace Tomkirsch\SuperImage\Tests\Unit;

use Tomkirsch\SuperImage\SuperImageWidths;
use Tomkirsch\SuperImage\Tests\TestCase;

class BuilderTest extends TestCase
{
	public function testBuilderAutoloadsWithoutLoadingSuperImage(): void
	{
		$code = 'require "vendor/autoload.php"; '
			. 'exit(class_exists("Tomkirsch\\SuperImage\\SuperImageWidths") '
			. '&& !class_exists("Tomkirsch\\SuperImage\\SuperImage", false) ? 0 : 1);';
		exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code), $output, $status);
		$this->assertSame(0, $status);
	}

	public function testReadmeBuilderUsesFluidSizesAndWidthDescriptors(): void
	{
		$this->makeImage('featured.jpg', 2400, 1200);
		$widths = SuperImageWidths::make()->full()->at(800, 'half')->at(1024, 'third');
		$html = $this->superImage()->render(['src' => 'featured.jpg', 'widths' => $widths]);
		$this->assertStringNotContainsString('<picture', $html);
		$this->assertStringNotContainsString('-w1-', $html);
		$this->assertStringContainsString('sizes="(min-width: 1024px) 33.3333vw, (min-width: 800px) 50vw, 100vw"', $html);
		$this->assertStringContainsString(' 2400w', $html); // no 1320px viewport ceiling
		$this->assertStringContainsString(' 160w', $html);
		$this->assertSame([1024, 800, 0], array_keys($widths->resolve()));
		$this->assertSame(540, $widths->resolve()[0]);
	}

	public function testGuttersAndPixelSlots(): void
	{
		$widths = SuperImageWidths::make()->full()->px(1200, 360);
		$this->assertSame('(min-width: 1200px) 336px, max(1px, calc(100vw - 24px))', $widths->sizes(24));
		$this->assertSame('(min-width: 1200px) 1px, max(1px, calc(100vw - 999px))', $widths->sizes(999));
		$this->assertSame('100vw', SuperImageWidths::make()->sizes());
	}

	public function testLegacyResolveOverrideSignatureStillWorks(): void
	{
		$builder = new class extends SuperImageWidths {
			public function resolve(int $gutter = 0): array { return [0 => 300]; }
		};
		$this->assertSame([0 => 300], $builder->resolve());
		$this->makeImage('a.jpg', 800, 400);
		$this->assertStringContainsString('<img', $this->superImage()->render(['src' => 'a.jpg', 'widths' => $builder]));
	}

	public function testInvalidBuilderValuesFailClearly(): void
	{
		foreach ([fn() => SuperImageWidths::make()->at(-1, 'half'),
			fn() => SuperImageWidths::make()->at(0, 0.0),
			fn() => SuperImageWidths::make()->at(0, INF),
			fn() => SuperImageWidths::make()->at(0, NAN),
			fn() => SuperImageWidths::make()->at(0, 1.1),
			fn() => SuperImageWidths::make()->px(0, 0)] as $call) {
			try { $call(); $this->fail('Expected invalid builder value'); }
			catch (\InvalidArgumentException) { $this->addToAssertionCount(1); }
		}
	}
}
