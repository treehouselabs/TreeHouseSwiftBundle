<?php

$file = __DIR__.'/../vendor/autoload.php';
if (!file_exists($file)) {
    throw new RuntimeException('Install dependencies to run test suite.');
}

$loader = require $file;
$loader->addPsr4('TreeHouse\\KeystoneIntegrationBundle\\', __DIR__ . '/../vendor/treehouselabs/keystone-bundle/tests/Functional/src/TreeHouse/KeystoneIntegrationBundle');
$loader->addPsr4('TreeHouse\\SwiftIntegrationBundle\\', __DIR__ . '/Functional/src/TreeHouse/SwiftIntegrationBundle');
