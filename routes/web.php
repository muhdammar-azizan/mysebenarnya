<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified', 'role:public'])->group(function () {
    Volt::route('inquiries/create', 'public.inquiries.create')->name('inquiries.create');
    Volt::route('inquiries', 'public.inquiries.index')->name('inquiries.index');
    Volt::route('inquiries/{inquiry}', 'public.inquiries.show')->name('inquiries.show');

    Volt::route('browse', 'public.browse.index')->name('browse.index');
    Volt::route('browse/{inquiry}', 'public.browse.show')->name('browse.show');

    Volt::route('notifications', 'public.notifications.index')->name('notifications.index');
});

require __DIR__.'/auth.php';
