<?php

// Pin the Laravel application base path so plugin autoloaders cannot
// pollute Application::inferBasePath() during the test run. Without this,
// once plugins/gaeld-ee/vendor/autoload.php registers a ClassLoader,
// inferBasePath() may resolve to the plugin directory and cause
// "Failed opening required '.../plugins/gaeld-ee/bootstrap/app.php'".
$basePath = dirname(__DIR__);
$_ENV['APP_BASE_PATH'] = $basePath;
$_SERVER['APP_BASE_PATH'] = $basePath;
putenv('APP_BASE_PATH='.$basePath);

require __DIR__.'/../vendor/autoload.php';
