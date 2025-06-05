<?php

require_once 'vendor/autoload.php';

use App\Services\DatabaseExplorerService;

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$service = new DatabaseExplorerService();

// Test de détection des clés étrangères
$queryResults = [
    ['id' => 1, 'title' => 'Test', 'user_id' => 1],
    ['id' => 2, 'title' => 'Test 2', 'user_id' => 2]
];

echo "Test de détection des clés étrangères:\n";
$foreignKeys = $service->detectForeignKeyColumns($queryResults, 'adminforge');
var_dump($foreignKeys);

// Test de récupération d'informations de tooltip
echo "\nTest de récupération d'informations de tooltip:\n";
$tooltipInfo = $service->getRecordTooltipInfo('adminforge', 'users', 'id', 1);
var_dump($tooltipInfo);
