<?php

namespace Tomkirsch\SuperImage\Tests\Unit;

use Tomkirsch\SuperImage\SuperImageWidths;
use Tomkirsch\SuperImage\Tests\TestCase;

/** Every URL emitted by render() must be canonical for the Resizer (snapped, no redirect needed). */
class RoundTripTest extends TestCase
{
	private function urls(string $html): array
	{
		preg_match_all('#https?://[^\s",]+?-w(\d+)-v\d+\.\w+#', $html, $m, PREG_SET_ORDER);
		return $m;
	}

	public function testEmittedWidthsAreCanonical(): void
	{
		class_exists(\Tomkirsch\SuperImage\SuperImage::class); // SuperImageWidths lives in SuperImage.php (autoload)
		$cases = [
			'preset' => ['widths' => 'full'],
			'half' => ['widths' => 'half', 'gutter' => 15],
			'builder' => ['widths' => SuperImageWidths::make()->full()->at(800, 'half')->at(1024, 'third')],
			'list' => ['widths' => [0 => 333, 768 => 517], 'static' => true],
			'static preset' => ['widths' => 'third', 'static' => true, 'maxResolution' => 3.0, 'resolutionStep' => 0.25],
		];
		foreach ([[1500, 900, false], [500, 300, false], [2000, 1000, true]] as [$w, $h, $up]) {
			$this->config->allowUpscale = $up;
			$this->config->maxSize = 1800;
			$this->makeImage("i{$w}.jpg", $w, $h);
			foreach ($cases as $name => $opts) {
				$html = $this->superImage()->render(['src' => "i{$w}.jpg"] + $opts);
				$urls = $this->urls($html);
				$this->assertNotEmpty($urls, $name);
				foreach ($urls as $u) {
					$req = $this->config->parseImageRequest(substr($u[0], strpos($u[0], '/img/') + 5));
					$this->assertNotNull($req, $u[0]);
					$this->assertSame(
						(int)$u[1],
						$this->config->snapWidth((int)$u[1], $w),
						"$name src={$w}: {$u[0]}"
					);
				}
			}
		}
	}
}
