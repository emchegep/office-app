<?php

use App\Http\Controllers\OfficeController;
use App\Http\Controllers\OfficeImageController;
use App\Http\Controllers\TagController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Tags
Route::get('/tags', TagController::class);

// Offices
Route::get('/offices', [OfficeController::class, 'index']);
Route::get('/offices/{office}', [OfficeController::class, 'show']);
Route::post('/offices', [OfficeController::class, 'create'])
    ->middleware('auth:sanctum','verified');
Route::put('/offices/{office}', [OfficeController::class, 'update'])
    ->middleware('auth:sanctum','verified');
Route::delete('/offices/{office}', [OfficeController::class, 'destroy'])
    ->middleware('auth:sanctum','verified');

// Photos
Route::post('/offices/{office}/images', [OfficeImageController::class, 'store'])
    ->middleware('auth:sanctum','verified');
Route::delete('/offices/{office}/images/{image}', [OfficeImageController::class,
    'delete'])->middleware('auth:sanctum','verified');
