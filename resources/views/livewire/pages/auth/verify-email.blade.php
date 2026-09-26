<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.auth')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<x-auth-card>
    <div class="text-center">
        <div class="w-16 h-16 rounded-full bg-brand-light flex items-center justify-center mx-auto mb-5">
            <div class="w-[30px] h-[22px] border-[2.5px] border-brand rounded relative">
                <div class="absolute top-0 left-0 w-[15px] h-[2.5px] bg-brand" style="transform: rotate(28deg); transform-origin: top left;"></div>
                <div class="absolute top-0 right-0 w-[15px] h-[2.5px] bg-brand" style="transform: rotate(-28deg); transform-origin: top right;"></div>
            </div>
        </div>

        <div class="font-display font-bold text-2xl mb-2.5">{{ __('Verify Your Email') }}</div>
        <div class="text-sm text-gray-500 leading-relaxed mb-6">
            {{ __("We've sent a verification link to your email. Please check your inbox to activate your account.") }}
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="mb-5 text-sm font-medium text-green-600">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </div>
        @endif

        <div class="flex flex-col gap-4">
            <button type="button" wire:click="sendVerification" class="h-10 rounded-lg bg-white text-gray-900 border-[1.5px] border-gray-300 font-semibold text-sm hover:bg-gray-50">
                {{ __('Resend Verification Email') }}
            </button>

            <button type="button" wire:click="logout" class="text-[13px] text-brand font-medium">
                {{ __('Log Out') }}
            </button>
        </div>
    </div>
</x-auth-card>
