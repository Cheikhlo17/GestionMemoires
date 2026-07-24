<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Lookup\LookupController;
use App\Http\Controllers\Api\Student\StudentController;
use App\Http\Controllers\Api\Thesis\ThesisCommentController;
use App\Http\Controllers\Api\Thesis\ThesisController;
use App\Http\Controllers\Api\Thesis\ThesisVersionController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed'])
        ->name('verification.verify');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::post('profile', [AuthController::class, 'updateProfile']);
        Route::post('resend-verification', [AuthController::class, 'resendVerification']);
    });
});

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::apiResource('students', StudentController::class);

    Route::apiResource('theses', ThesisController::class);
    Route::post('theses/{thesis}/submit', [ThesisController::class, 'submit']);
    Route::post('theses/{thesis}/assign-supervisor', [ThesisController::class, 'assignSupervisor']);
    Route::post('theses/{thesis}/status', [ThesisController::class, 'changeStatus']);

    Route::get('theses/{thesis}/versions', [ThesisVersionController::class, 'index']);
    Route::post('theses/{thesis}/versions', [ThesisVersionController::class, 'store']);
    Route::get('theses/{thesis}/versions/{version}/download', [ThesisVersionController::class, 'download']);

    Route::get('theses/{thesis}/comments', [ThesisCommentController::class, 'index']);
    Route::post('theses/{thesis}/comments', [ThesisCommentController::class, 'store']);

    Route::prefix('lookups')->group(function () {
        Route::get('departments', [LookupController::class, 'departments']);
        Route::get('programs', [LookupController::class, 'programs']);
        Route::get('academic-years', [LookupController::class, 'academicYears']);
    });
});