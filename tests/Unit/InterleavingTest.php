<?php

namespace Tomkirsch\SuperImage\Tests\Unit;

use CodeIgniter\Images\Handlers\GDHandler;
use Tomkirsch\SuperImage\Resizer;
use Tomkirsch\SuperImage\Tests\TestCase;

class InterleavingResizer extends Resizer
{
	public function run(object $request, string $source, string $cache, ?GDHandler $lib = null): void
	{
		$this->imageLib = $lib;
		$this->doResize($request, $source, $cache);
	}
}

/** Deterministic interleaving: writer A has published the current file, then writer B runs doResize(). */
class InterleavingTest extends TestCase
{
	public function testSecondWriterDoesNotDeleteCompletedCurrentFile(): void
	{
		$this->makeImage('a.jpg', 1200, 800);
		$r = new InterleavingResizer($this->config);
		$req = $this->config->parseImageRequest($this->reqPath('a.jpg', 400));
		$cache = $this->config->prepareCachePath($req);
		$src = $this->src . '/a.jpg';

		$r->run($req, $src, $cache);                 // writer A completes
		$a = file_get_contents($cache);

		// Writer B: the handler hook runs at B's first image operation, i.e. after any cleanup in doResize()
		$existed = null;
		$lib = new class($this->config->cachePath, $cache, $existed) extends GDHandler {
			public function __construct(string $unused, private string $watch, public &$existed)
			{
				parent::__construct(new \Config\Images());
			}
			public function withFile(string $path)
			{
				$this->existed = file_exists($this->watch);
				return parent::withFile($path);
			}
		};
		$r->run($req, $src, $cache, $lib);
		$this->assertTrue($existed, "writer A's current file was deleted by writer B");
		$this->assertSame($a, file_get_contents($cache));
		$this->assertSame([basename($cache)], array_map('basename', $this->cacheFiles()));
	}
}
