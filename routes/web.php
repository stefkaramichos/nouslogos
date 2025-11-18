<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProfessionalController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\PaymentController;

Route::get('/', function () {
    return redirect()->route('appointments.index');
});

Route::resource('customers', CustomerController::class);
Route::resource('professionals', ProfessionalController::class);
Route::resource('appointments', AppointmentController::class);
Route::get('appointments/{appointment}/payment', [PaymentController::class, 'edit'])
    ->name('appointments.payment.edit');

Route::post('appointments/{appointment}/payment', [PaymentController::class, 'update'])
    ->name('appointments.payment.update');

    Route::get('/professionals/{professional}', [ProfessionalController::class, 'show'])
    ->name('professionals.show');
