<?php

/**
 * Boots the REAL CodeIgniter 4 framework (from vendor/) the same way `codeigniter4/framework`'s own
 * test suite does, so base_url(), Services, response(), PageNotFoundException etc. are the genuine
 * framework classes (no stubs). Must be run from the repository root.
 */

chdir(dirname(__DIR__));

require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';
require __DIR__ . '/../vendor/autoload.php';
