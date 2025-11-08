<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Define login route to prevent "Route [login] not defined" error
// This route is only used for web requests, API requests are handled by exception handler
Route::get('/login', function () {
    return redirect('/');
})->name('login');
