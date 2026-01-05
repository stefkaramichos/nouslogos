<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProfessionalController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Hash;
use App\Models\Professional;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\TherapistAppointmentController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\AppointmentTrashController;
use App\Http\Controllers\NotificationController;

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
        $user = auth()->user();

        if ($user->role === 'therapist') {
            return redirect()->route('therapist_appointments.index'); // /my-appointments
        }

        return redirect()->route('customers.index'); // owner, grammatia, etc
    })->name('dashboard');


    Route::get('/professionals/company', [\App\Http\Controllers\ProfessionalController::class, 'getCompany'])
    ->name('professionals.getCompany');

    Route::get('/appointments/recycle', [AppointmentTrashController::class, 'index'])
            ->name('appointments.recycle');

    Route::post('/appointments/{appointment}/restore', [AppointmentTrashController::class, 'restore'])
            ->name('appointments.restore');

    Route::delete('/appointments/{appointment}/force', [AppointmentTrashController::class, 'forceDelete'])
            ->name('appointments.forceDelete');

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

    Route::delete('/customers/{customer}/appointments/delete-selected', [CustomerController::class, 'deleteAppointments'])
    ->name('customers.deleteAppointments');

    Route::get('/api/customers/last-appointment', [AppointmentController::class, 'getLastForCustomer'])
    ->name('customers.lastAppointment');

 
    Route::get('/settlements', [SettlementController::class, 'index'])
            ->name('settlements.index');
    Route::post('/settlements/settle', [SettlementController::class, 'store'])->name('settlements.store');

    Route::resource('expenses', ExpenseController::class)
    ->middleware(['auth', 'role:owner,grammatia']);

    Route::patch(
            '/professionals/{professional}/toggle-active',
            [ProfessionalController::class, 'toggleActive']
        )->name('professionals.toggle-active');
        
        
            // Ραντεβού θεραπευτή (νέο table)
            Route::get('/my-appointments', [TherapistAppointmentController::class, 'index'])
                ->name('therapist_appointments.index');
        
            Route::get('/my-appointments/create', [TherapistAppointmentController::class, 'create'])
                ->name('therapist_appointments.create');
        
            Route::post('/my-appointments', [TherapistAppointmentController::class, 'store'])
                ->name('therapist_appointments.store');
                
            Route::get('/my-appointments/{therapistAppointment}/edit', [TherapistAppointmentController::class, 'edit'])
                ->name('therapist_appointments.edit');
        
            Route::put('/my-appointments/{therapistAppointment}', [TherapistAppointmentController::class, 'update'])
                ->name('therapist_appointments.update');
        
            Route::delete('/my-appointments/{therapistAppointment}', [TherapistAppointmentController::class, 'destroy'])
                ->name('therapist_appointments.destroy');
         
            Route::post('/appointments/{appointment}/update-price', [AppointmentController::class, 'updatePrice'])
                 ->name('appointments.updatePrice');


    Route::get('/notifications/due', [NotificationController::class, 'due'])->name('notifications.due');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::resource('notifications', NotificationController::class)->except(['show']);
 
    Route::post('/customers/{customer}/files', [CustomerController::class, 'uploadFile'])
        ->name('customers.files.store');

    Route::get('/customers/{customer}/files/{file}/download', [CustomerController::class, 'downloadFile'])
        ->name('customers.files.download');

    Route::delete('/customers/{customer}/files/{file}', [CustomerController::class, 'deleteFile'])
        ->name('customers.files.destroy');
       
    Route::get(
        '/customers/{customer}/files/{file}/view',
        [CustomerController::class, 'view']
    )->name('customers.files.view');

    Route::get(
        '/customers/{customer}/payment-preview',
        [CustomerController::class, 'paymentPreview']
    )->name('customers.paymentPreview');

    Route::post('/customers/{customer}/pay-all-split', [CustomerController::class, 'payAllSplit'])
        ->name('customers.payAllSplit');

    Route::get('/customers/{customer}/payment-preview', [CustomerController::class, 'paymentPreview'])
        ->name('customers.paymentPreview');


});

Route::get('/hash-password', [\App\Http\Controllers\HashController::class, 'hashPassword']);
// routes/web.php

