<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TravelTipsController;

Route::post('/travel-tips', [TravelTipsController::class, 'generate']);
