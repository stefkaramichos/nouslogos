<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProfessionalController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\AuthController;

// 🔹 Login routes (χωρίς auth)
Route::get('/login', [AuthController::class, 'showLoginForm'])
    ->name('login')
    ->middleware('guest');

Route::post('/login', [AuthController::class, 'login'])
    ->name('login.post')
    ->middleware('guest');

Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');

// 🔹 Όλες οι υπόλοιπες σελίδες ΜΟΝΟ για logged-in users
Route::middleware('auth')->group(function () {

    // Αρχική -> ραντεβού
    Route::get('/', function () {
        return redirect()->route('appointments.index');
    })->name('dashboard');

    // Πελάτες / Επαγγελματίες / Ραντεβού
    Route::resource('customers', CustomerController::class);
    Route::resource('professionals', ProfessionalController::class);
    Route::resource('appointments', AppointmentController::class);

    // Πληρωμές ραντεβού
    Route::get('appointments/{appointment}/payment', [PaymentController::class, 'edit'])
        ->name('appointments.payment.edit');

    Route::post('appointments/{appointment}/payment', [PaymentController::class, 'update'])
        ->name('appointments.payment.update');

    Route::post('/customers/{customer}/pay-all', [CustomerController::class, 'payAll'])
    ->name('customers.payAll');

    Route::get('/api/customers/last-appointment', [AppointmentController::class, 'getLastForCustomer'])
    ->name('customers.lastAppointment');

});

