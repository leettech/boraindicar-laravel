<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/boraindicar');

// Workbench has no auth UI: "login" signs user #1 in through /_workbench.
Route::redirect('login', '/_workbench')->name('login');
