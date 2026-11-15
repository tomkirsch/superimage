<?php

/**
 * Router for the loopback-only PHP built-in server used by HttpTest.
 * Boots the real CI4 framework, then maps /img/{path} to Resizer::serve() exactly like the README controller.
 * Configured through env vars SI_SRC / SI_CACHE (set by the test; fixtures only).
 */

use CodeIgniter\Exceptions\PageNotFoundException;

chdir(dirname(__DIR__, 2));
require dirname(__DIR__, 2) . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';
require dirname(__DIR__, 2) . '/vendor/autoload.php';

$uri = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (!str_starts_with($uri, '/img/')) {
	http_response_code(404);
	echo 'not found';
	return;
}

$cfg = new \Tomkirsch\SuperImage\SuperImageConfig();
$cfg->sourcePath = getenv('SI_SRC') . '/';
$cfg->cachePath = getenv('SI_CACHE') . '/';

try {
	(new \Tomkirsch\SuperImage\Resizer($cfg))->serve(substr($uri, 5));
} catch (PageNotFoundException) {
	http_response_code(404);
	header('Content-Type: text/plain');
	echo 'Not found';
} catch (\Throwable $e) {
	http_response_code(500);
	header('Content-Type: text/plain');
	echo 'error'; error_log(get_class($e) . ': ' . $e->getMessage());
}
