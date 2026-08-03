<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route temporaire pour lire les logs de Laravel
Route::get('/debug-logs', function () {
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        // Affiche le contenu du fichier log directement dans le navigateur
        return response()->file($logFile, [
            'Content-Type' => 'text/plain',
        ]);
    }
    return 'Aucun fichier de log trouvé.';
});