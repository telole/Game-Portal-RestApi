<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\GameController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::post('auth/signup', [AuthController::class, 'signup']);
    Route::post('auth/signin', [AuthController::class, 'signin']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/signout', [AuthController::class, 'signout']);

        Route::get('users/{name}', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::put('users/{id}', [UserController::class, 'update']);
        Route::delete('users/{id}', [UserController::class, 'destroy']);

        Route::post('/games', [GameController::class, 'store']);
        Route::put('/games/{slug}/scores', [GameController::class, 'update']);
        Route::put('/games/{slug}', [GameController::class, 'update']);
        Route::delete('/games/{slug}', [GameController::class, 'destroy']);
        Route::post('/games/{slug}/upload', [GameController::class, 'uploadVersion']);
        Route::post('/games/{slug}/scores', [GameController::class, 'postScore']);
    });

    Route::get('/games', [GameController::class, 'index']);
    Route::get('/games/{slug}', [GameController::class, 'show']);
    Route::get('/games/{slug}/scores', [GameController::class, 'getScores']);

    Route::fallback(function () {
        return response()->json([
            'status' => 'not found',
            'message' => 'Not Found'
        ], 404);
    });
});