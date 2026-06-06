<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/login')->name('admin');
