<?php

namespace Tomkirsch\SuperImage;

/**
 * Fluent builder for responsive image width breakpoints.
 *
 * Usage:
 *   SuperImageWidths::make()
 *       ->full()            // 0px+: full container width
 *       ->at(800, 'half')   // 800px+: half container width
 *       ->at(1024, 'third') // 1024px+: one-third container width
 */
class SuperImageWidths
{
	protected array $breakpoints = [];

	protected const PRESETS = [
		'full'       => 1.0,
		'half'       => 0.5,
		'third'      => 1 / 3,
		'quarter'    => 0.25,
		'two-thirds' => 2 / 3,
	];

	public static function make(): self
	{
		return new self();
	}

	/**
	 * Add a breakpoint. $width is the min-width in px (0 = mobile default).
	 * $fraction can be a preset name ('half', 'third', etc.) or a float (0.0–1.0).
	 */
	public function at(int $minWidth, string|float $fraction): self
	{
		if ($minWidth < 0) {
			throw new \InvalidArgumentException('Breakpoint must be non-negative');
		}
		$this->breakpoints[$minWidth] = $this->resolveFraction($fraction);
		return $this;
	}

	// Shorthand helpers
	public function full(int $minWidth = 0): self
	{
		return $this->at($minWidth, 1.0);
	}
	public function half(int $minWidth = 0): self
	{
		return $this->at($minWidth, 0.5);
	}
	public function third(int $minWidth = 0): self
	{
		return $this->at($minWidth, 1 / 3);
	}
	public function quarter(int $minWidth = 0): self
	{
		return $this->at($minWidth, 0.25);
	}
	public function twoThirds(int $minWidth = 0): self
	{
		return $this->at($minWidth, 2 / 3);
	}

	public function px(int $minWidth, int $pixels): self
	{
		if ($minWidth < 0 || $pixels < 1) {
			throw new \InvalidArgumentException('Breakpoint must be non-negative and pixels positive');
		}
		$this->breakpoints[$minWidth] = ['px' => $pixels];
		return $this;
	}

	/**
	 * Resolve nominal pixel widths (540px mobile viewport) for the fallback.
	 * Fluid layout sizes are expressed separately in viewport units.
	 */
	public function resolve(int $gutter = 0): array
	{
		krsort($this->breakpoints);
		$widths = [];

		foreach ($this->breakpoints as $minWidth => $value) {
			if (is_array($value)) {
				$widths[$minWidth] = max(1, $value['px'] - $gutter);
			} else {
				$widths[$minWidth] = max(1, (int)(($minWidth ?: 540) * $value) - $gutter);
			}
		}

		if (!isset($widths[0])) {
			$widths[0] = max(1, 540 - $gutter);
		}
		return $widths;
	}

	/** Browser slot sizes, widest min-width first; fractions stay fluid above each breakpoint. */
	public function sizes(int $gutter = 0): string
	{
		$breakpoints = $this->breakpoints + [0 => 1.0];
		krsort($breakpoints);
		$sizes = [];
		foreach ($breakpoints as $minWidth => $value) {
			if (is_array($value)) {
				$length = max(1, $value['px'] - $gutter) . 'px';
			} else {
				$length = rtrim(rtrim(sprintf('%.4F', $value * 100), '0'), '.') . 'vw';
				if ($gutter !== 0) {
					$length = 'max(1px, calc(' . $length . ($gutter > 0 ? ' - ' : ' + ') . abs($gutter) . 'px))';
				}
			}
			$sizes[] = ($minWidth > 0 ? "(min-width: {$minWidth}px) " : '') . $length;
		}
		return implode(', ', $sizes);
	}

	protected function resolveFraction(string|float $fraction): float
	{
		if (is_string($fraction)) {
			if (!isset(self::PRESETS[$fraction])) {
				throw new \InvalidArgumentException("Unknown SuperImageWidths preset: {$fraction}");
			}
			return self::PRESETS[$fraction];
		}
		if (!is_finite($fraction) || $fraction <= 0 || $fraction > 1) {
			throw new \InvalidArgumentException('Fraction must be finite and between 0 (exclusive) and 1');
		}
		return $fraction;
	}
}

