<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Kirby's Whoops error handler stays registered after every App is created, so
// the risky-test check would flag each Kirby test. PHPUnit reports errors itself.
Kirby\Cms\App::$enableWhoops = false;

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/lib/Fixture.php';
