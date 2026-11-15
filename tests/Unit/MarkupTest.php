<?php

namespace Tomkirsch\SuperImage\Tests\Unit;

use Tomkirsch\SuperImage\SuperImage;
use Tomkirsch\SuperImage\Tests\TestCase;

class MarkupTest extends TestCase
{
	public function testPhoneFallbackHasHighDensitySrcset(): void
	{
		$this->makeImage('phone.jpg', 2400, 1200);
		$html = $this->superImage()->render(['src' => 'phone.jpg', 'widths' => 'half']);
		$this->assertStringContainsString('<picture', $html);
		preg_match('/<img[^>]+>/', $html, $m);
		$this->assertStringContainsString('srcset=', $m[0]);
		$this->assertStringContainsString(' 2x', $m[0]);
		$this->assertStringContainsString('-w560-', $m[0]);
	}

	public function testFixedListsIncludingSingletonUseFixedSizesAndDimensions(): void
	{
		$this->makeImage('a.jpg', 2400, 1200);
		foreach ([[100, 200, 300], [150], [300, 100]] as $widths) {
			$html = $this->superImage()->render(['src' => 'a.jpg', 'widths' => $widths]);
			$this->assertStringNotContainsString('<picture', $html);
			$this->assertStringContainsString('srcset=', $html);
			$this->assertStringContainsString('sizes="' . min($widths) . 'px"', $html);
			$this->assertStringContainsString('width="' . min($widths) . '"', $html);
			$this->assertStringContainsString('height="' . (min($widths) / 2) . '"', $html);
			$this->assertStringNotContainsString('min-width:', $html);
		}
	}

	public function testOversizedLayoutsClampRatherThanDropAndSizesAreNotBarePx(): void
	{
		$this->makeImage('small.jpg', 300, 150);
		$html = $this->superImage()->render(['src' => 'small.jpg', 'static' => true]);
		$this->assertStringNotContainsString(') px', $html);
		$this->assertStringContainsString('(min-width: 1400px) 300px', $html);
		preg_match_all('/-w(\d+)-/', $html, $m);
		$this->assertSame([300], array_values(array_unique(array_map('intval', $m[1]))));
	}

	public function testLargeGutterNeverMakesNegativeCandidates(): void
	{
		$this->makeImage('g.jpg', 1000, 500);
		foreach (['half', 0.5] as $widths) {
			$html = $this->superImage()->render(['src' => 'g.jpg', 'widths' => $widths, 'gutter' => 5000]);
			$this->assertStringNotContainsString('-w-', $html);
			$this->assertStringNotContainsString('-w0-', $html);
			$this->assertStringContainsString('-w160-', $html);
		}
	}

	public function testEmptyRowsAreNotSizesEntries(): void
	{
		$si = new class($this->config) extends SuperImage {
			public function sizesForEmptyRow(): string {
				$this->resolutionDict = [1400 => [], 768 => ['1' => 400], 0 => ['1' => 200]];
				return $this->generateSizesAttribute();
			}
		};
		$this->assertSame('(min-width: 768px) 400px, 200px', $si->sizesForEmptyRow());
	}

	public function testFractionalStepIncludesExpectedEndpointWithoutFloatAccumulation(): void
	{
		$this->makeImage('r.jpg', 2400, 1200);
		$html = $this->superImage()->render(['src' => 'r.jpg', 'widths' => 'quarter', 'maxResolution' => 2.0, 'resolutionStep' => 0.1]);
		$this->assertStringContainsString(' 2x', $html);
		$this->assertStringNotContainsString('2.000000', $html);
	}

	public function testInvalidResolutionOptionsRejectPromptlyInTimeoutProcess(): void
	{
		foreach ([['resolutionStep' => 0], ['resolutionStep' => -1], ['resolutionStep' => INF],
			['resolutionStep' => NAN], ['maxResolution' => 0.5], ['maxResolution' => INF],
			['maxResolution' => NAN], ['resolutionStep' => 1e-300], ['maxResolution' => 1e300]] as $options) {
			$code = 'require "tests/bootstrap.php"; try { (new \\Tomkirsch\\SuperImage\\SuperImage())->render('
				. var_export(['src' => 'unused.jpg'] + $options, true)
				. '); exit(1); } catch (\\InvalidArgumentException $e) { exit(0); }';
			exec('timeout 3s ' . escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code), $output, $status);
			$this->assertSame(0, $status, 'Invalid options must reject, not time out');
		}
	}
}
