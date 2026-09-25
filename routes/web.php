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

Route::middleware(['auth', 'verified', 'role:mcmc_staff'])->prefix('mcmc')->name('mcmc.')->group(function () {
    Volt::route('triage', 'mcmc.triage.index')->name('triage.index');

    Volt::route('inquiries', 'mcmc.inquiries.index')->name('inquiries.index');
    Volt::route('inquiries/{inquiry}', 'mcmc.inquiries.show')->name('inquiries.show');

    Volt::route('agencies', 'mcmc.agencies.index')->name('agencies.index');
    Volt::route('agencies/create', 'mcmc.agencies.create')->name('agencies.create');
    Volt::route('agencies/{agency}/edit', 'mcmc.agencies.edit')->name('agencies.edit');

    Volt::route('clarifications', 'mcmc.clarifications.index')->name('clarifications.index');
    Volt::route('clarifications/{thread}', 'mcmc.clarifications.show')->name('clarifications.show');

    Volt::route('users', 'mcmc.users.index')->name('users.index');
    Volt::route('reports', 'mcmc.reports.index')->name('reports.index');
    Volt::route('notifications', 'mcmc.notifications.index')->name('notifications.index');
});

Route::middleware(['auth', 'verified', 'role:agency_staff'])->prefix('agency')->name('agency.')->group(function () {
    Volt::route('inquiries', 'agency.inquiries.index')->name('inquiries.index');
    Volt::route('inquiries/{inquiry}', 'agency.inquiries.show')->name('inquiries.show');

    Volt::route('consultations', 'agency.consultations.index')->name('consultations.index');
    Volt::route('consultations/{consult}', 'agency.consultations.show')->name('consultations.show');

    Volt::route('reports', 'agency.reports.index')->name('reports.index');
    Volt::route('activity', 'agency.activity.index')->name('activity.index');
    Volt::route('organization', 'agency.organization.index')->name('organization.index');
    Volt::route('notifications', 'agency.notifications.index')->name('notifications.index');
});

require __DIR__.'/auth.php';
