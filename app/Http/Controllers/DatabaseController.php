<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DatabaseExplorerService;
use Illuminate\Http\JsonResponse;

class DatabaseController extends Controller
{
    protected DatabaseExplorerService $databaseService;

    public function __construct(DatabaseExplorerService $databaseService)
    {
        $this->databaseService = $databaseService;
    }

    /**
     * Créer une nouvelle base de données
     */
    public function createDatabase(Request $request): JsonResponse
    {
        $request->validate([
            'database_name' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/'
            ]
        ], [
            'database_name.regex' => 'Le nom doit commencer par une lettre ou un underscore et ne contenir que des lettres, chiffres et underscores.'
        ]);

        $result = $this->databaseService->createDatabase($request->database_name);

        return response()->json($result);
    }
}
