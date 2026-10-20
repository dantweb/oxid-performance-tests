<?php

/**
 * Runs inside the shop: PHPUnit comes from the shop's vendor, the package is autoloaded from here,
 * so the shop's composer.json does not have to require it.
 */

declare(strict_types=1);

$loader = require __DIR__ . '/../../../vendor/autoload.php';
$loader->addPsr4('Dantweb\\OxidPerformanceTests\\', __DIR__ . '/../src');
$loader->addPsr4('Dantweb\\OxidPerformanceTests\\Tests\\', __DIR__);
