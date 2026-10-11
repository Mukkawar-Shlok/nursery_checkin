<?php

use App\Http\Controllers\CheckInController;
use App\Http\Controllers\RoomOccupantController;
use Illuminate\Support\Facades\Route;

// Every route here runs ResolveTenant (api group, see bootstrap/app.php).
Route::post('/rooms/{room}/check-ins', [CheckInController::class, 'store'])->whereUuid('room');
Route::post('/check-ins/{checkIn}/check-out', [CheckInController::class, 'checkOut'])->whereUuid('checkIn');
Route::get('/rooms/{room}/occupants', [RoomOccupantController::class, 'index'])->whereUuid('room');
