<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DatabaseController;

// Route pour créer une base de données depuis la sidebar
Route::middleware(['auth'])->group(function () {
    Route::post('/admin/create-database', [DatabaseController::class, 'createDatabase']);
});
