<?php

use Illuminate\Support\Facades\Route;
use Modules\ContactCenter\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->prefix('contact-center')->group(function () {
    Route::post('user', [UserController::class, 'store'])->name('contact-center.user.store');
});
