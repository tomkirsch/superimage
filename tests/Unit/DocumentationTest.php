<?php

namespace Tomkirsch\SuperImage\Tests\Unit;

use Tomkirsch\SuperImage\Tests\TestCase;

class DocumentationTest extends TestCase
{
	public function testActualDocumentedRenderSnippetsDoNotThrow(): void
	{
		foreach (['hero.jpg', 'featured.jpg', 'avatar.jpg', 'product_1.jpg', 'product_2.jpg',
			'product_3.jpg', 'product_4.jpg', 'gallery/photo-1.jpg', 'gallery/photo-2.jpg', 'gallery/photo-3.jpg'] as $name) {
			$this->makeImage($name, 1200, 800);
		}
		$count = 0;
		$fence = str_repeat(chr(96), 3);
		foreach (['README.md', 'EXAMPLES.md'] as $doc) {
			$markdown = file_get_contents(dirname(__DIR__, 2) . '/' . $doc);
			preg_match_all('/' . $fence . 'php\R(.*?)' . $fence . '/s', $markdown, $matches);
			foreach ($matches[1] as $snippet) {
				if (!str_contains($snippet, '->render(')) { continue; }
				// Replace only the app-specific service factory; execute the actual options and PHP.
				$snippet = str_replace('\\Config\\Services::superImage()', '$si', $snippet);
				$si = $this->superImage();
				ob_start();
				try { eval('?>' . $snippet); $html = ob_get_contents(); }
				finally { ob_end_clean(); }
				$this->assertStringContainsString('<img', $html, $doc);
				$this->assertStringNotContainsString('<svg', $html, $doc);
				$count++;
			}
		}
		$this->assertGreaterThanOrEqual(12, $count);
	}

	public function testApacheFastPathMatchesOnlySupportedOutputFormats(): void
	{
		$readme = file_get_contents(dirname(__DIR__, 2) . '/README.md');
		preg_match('/RewriteRule (\^img\/\S+)/', $readme, $match);
		$this->assertNotEmpty($match);
		$pattern = '~' . $match[1] . '~';
		foreach (['webp', 'jpg', 'jpeg', 'png'] as $ext) {
			$this->assertSame(1, preg_match($pattern, 'img/photo.jpg-w400-v123.' . $ext));
		}
		foreach (['avif', 'php', 'html', 'gif', 'svg'] as $ext) {
			$this->assertSame(0, preg_match($pattern, 'img/photo.jpg-w400-v123.' . $ext));
		}
		$this->assertStringContainsString('cleanAll()', $readme);
		$this->assertStringContainsString('dedicated', $readme);
		$this->assertStringContainsString('302', $readme);
	}
}
