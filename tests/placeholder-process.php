<?php

// Real CI4 console boot: unlike the test bootstrap, this honors our chosen environment.
chdir(dirname(__DIR__));
define('ENVIRONMENT', $argv[1]);
define('FCPATH', __DIR__ . '/');
require 'vendor/codeigniter4/framework/app/Config/Paths.php';
$paths = new \Config\Paths();
require $paths->systemDirectory . '/Boot.php';
\CodeIgniter\Boot::bootConsole($paths);
require 'vendor/autoload.php';

$logger = new class extends \Psr\Log\AbstractLogger {
	public array $messages = [];
	public function log($level, $message, array $context = []): void {
		$this->messages[] = [$level, (string)$message];
	}
};
\Config\Services::injectMock('logger', $logger);
$config = new \Tomkirsch\SuperImage\SuperImageConfig();
$config->sourcePath = $argv[2] . '/';
$html = (new \Tomkirsch\SuperImage\SuperImage($config))->render(['src' => 'missing.jpg']);
echo json_encode(['html' => $html, 'logs' => $logger->messages], JSON_THROW_ON_ERROR);
