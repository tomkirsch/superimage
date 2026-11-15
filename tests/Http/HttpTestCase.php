<?php

namespace Tomkirsch\SuperImage\Tests\Http;

use Tomkirsch\SuperImage\Tests\TestCase;

/**
 * Runs the PHP built-in server on 127.0.0.1 ONLY (random free port), routed through router.php.
 * The server is killed in tearDown.
 */
abstract class HttpTestCase extends TestCase
{
	/** @var resource|null */
	private $proc = null;
	protected int $port = 0;

	protected function startServer(int $workers = 1, array $extraEnv = []): void
	{
		$s = stream_socket_server('tcp://127.0.0.1:0', $en, $es);
		$this->port = (int)substr(strrchr(stream_socket_get_name($s, false), ':'), 1);
		fclose($s);
		$env = array_merge(getenv(), [
			'SI_SRC' => $this->src,
			'SI_CACHE' => $this->cache,
			'PHP_CLI_SERVER_WORKERS' => (string)$workers,
		] + $extraEnv);
		$cmd = [PHP_BINARY, '-S', '127.0.0.1:' . $this->port, __DIR__ . '/router.php'];
		$this->proc = proc_open($cmd, [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', $this->sandbox . '/server.log', 'w']], $pipes, dirname(__DIR__, 2), $env);
		for ($i = 0; $i < 100; $i++) {
			$c = @fsockopen('127.0.0.1', $this->port, $e1, $e2, 0.1);
			if ($c) {
				fclose($c);
				return;
			}
			usleep(50000);
		}
		$this->fail('server did not start');
	}

	protected function tearDown(): void
	{
		if ($this->proc) {
			$st = proc_get_status($this->proc);
			if ($st['running']) {
				// kill the whole group of children too (workers)
				@exec('pkill -P ' . (int)$st['pid'] . ' 2>/dev/null');
				proc_terminate($this->proc, 9);
			}
			proc_close($this->proc);
			$this->proc = null;
		}
		parent::tearDown();
	}

	/** @return array{0:int,1:string,2:array<string,string>} status, body, lower-cased headers */
	protected function get(string $path): array
	{
		$ctx = stream_context_create(['http' => ['follow_location' => 0, 'ignore_errors' => true, 'timeout' => 20]]);
		$body = @file_get_contents('http://127.0.0.1:' . $this->port . '/img/' . $path, false, $ctx);
		$status = 0;
		$headers = [];
		foreach ($http_response_header ?? [] as $h) {
			if (preg_match('#^HTTP/\S+ (\d+)#', $h, $m)) {
				$status = (int)$m[1];
			} elseif (str_contains($h, ':')) {
				[$k, $v] = explode(':', $h, 2);
				$headers[strtolower($k)] = trim($v);
			}
		}
		return [$status, (string)$body, $headers];
	}
}
