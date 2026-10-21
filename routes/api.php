<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/categories', [HomeController::class, 'FetchCategories']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['guest'])
    ->group(function () {
        Route::controller(AuthController::class)->group(function () {
            Route::post('/sign-up', 'Signup');
            Route::post('/sign-in', 'Signin');
            Route::post('/admin-sign-in', 'AdminSignin');
            Route::post('/email-check', 'emailCheck');
            Route::post('/code-reset', 'codeReset');
            Route::post('/change-pass', 'changePass');
        });
        Route::controller(HomeController::class)->group(function () {
            Route::get('/fetch-categories', 'FetchCategories');
            Route::post('/submit-request', 'SubmitRequest');
        });
    });

Route::middleware('auth:sanctum')->group(function () {
    Route::controller(AuthController::class)->group(function () {
        Route::post('/sign-out', 'Signout');
    });
    Route::controller(AccountController::class)->group(function () {
        Route::get('/account', 'Account');
        Route::patch('/update-account', 'UpdateAccount');
    });
    Route::controller(AdminController::class)->group(function () {
        Route::get('/view-requests', 'ViewRequests');
        Route::post('/mark-viewed', 'MarkViewed');
        Route::patch('/update-request', 'UpdateRequest');
        Route::post('/add-category', 'AddCategory');
        Route::get('/view-categories', 'ViewCategories');
        Route::patch('/update-category', 'UpdateCategory');
        Route::delete('/delete-category/{id}', 'DeleteCategory');
        Route::post('/add-sub-category', 'AddSubCategory');
        Route::get('/view-sub-categories', 'ViewSubCategories');
        Route::patch('/update-sub-category', 'UpdateSubCategory');
        Route::delete('/delete-sub-category/{id}', 'DeleteSubCategory');
    });
});
