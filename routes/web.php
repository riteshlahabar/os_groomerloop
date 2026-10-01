<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('frontview.home');
});

Route::get('/frontview', function () {
    return view('frontview.home');
});
