<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ApiChatController;

Route::post('/chat', [ApiChatController::class, 'handleMessage']);
