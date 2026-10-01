<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('frontview.home');
});

Route::get('/frontview', function () {
    return view('frontview.home');
});

Route::get('/login', function () {
    return view('frontview.login');
});

Route::get('/register', function () {
    return view('frontview.register');
});

Route::get('/pricing', function () {
    return view('frontview.pricing');
});

Route::get('/about-us', function () {
    return view('frontview.about-us');
});

Route::get('/contact-us', function () {
    return view('frontview.contact-us');
});
