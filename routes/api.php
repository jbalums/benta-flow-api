<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::get('/test', function () {
    return 'test';
});


Route::post('/auth/signup', [AuthController::class, 'signup']);
Route::post('/auth/signup/google', [AuthController::class, 'signupWithGoogle']);
Route::post('/auth/login', [AuthController::class, 'login']); //->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get("/auth/me", [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/store-details', [AuthController::class, 'upsertStoreDetails']);
    Route::apiResource('users', UserController::class);
    Route::apiResource('product-categories', ProductCategoryController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('branches', BranchController::class);
});
