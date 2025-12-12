<?php

use App\Http\Controllers\BillController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OtherIncomeController;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\TwoFactor;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::redirect('/', '/admin')->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('profile.edit');
    Route::get('settings/password', Password::class)->name('user-password.edit');
    Route::get('settings/appearance', Appearance::class)->name('appearance.edit');

    Route::get('settings/two-factor', TwoFactor::class)
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');

    Route::get('invoices/{invoice}/original-pdf', [InvoiceController::class, 'showOriginalPdf'])
        ->name('invoices.original-pdf');

    Route::get('invoices/{invoice}/download-pdf/{language?}', [InvoiceController::class, 'downloadPdf'])
        ->where('language', 'es|en')
        ->name('invoices.download-pdf');

    Route::get('invoices/{invoice}/show-pdf/{language?}', [InvoiceController::class, 'showPdf'])
        ->where('language', 'es|en')
        ->name('invoices.show-pdf');

    Route::get('bills/{bill}/original-pdf', [BillController::class, 'showOriginalPdf'])
        ->name('bills.original-pdf');

    Route::get('other-incomes/{otherIncome}/original-pdf', [OtherIncomeController::class, 'showOriginalPdf'])
        ->name('other-incomes.original-pdf');
});
