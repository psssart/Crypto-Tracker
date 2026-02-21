<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Jobs\UpdateWebhookAddress;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $telegramChat = $request->user()->telegramChat;

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'telegramLinked' => $telegramChat !== null,
            'telegramUsername' => $telegramChat?->username,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Generate a Telegram deep link token for account linking.
     */
    public function telegramLink(Request $request): JsonResponse
    {
        $token = Str::random(64);

        Cache::put("telegram_link:{$token}", $request->user()->id, now()->addMinutes(15));

        $botUsername = config('nutgram.bot_username');
        $url = "https://t.me/{$botUsername}?start={$token}";

        return response()->json(['url' => $url]);
    }

    /**
     * Unlink the user's Telegram account.
     */
    public function telegramUnlink(Request $request): RedirectResponse
    {
        $request->user()->telegramChat?->delete();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        $orphanedWallets = $user->wallets()
            ->withCount('users')
            ->get()
            ->filter(fn ($wallet) => $wallet->users_count === 1);

        foreach ($orphanedWallets as $wallet) {
            UpdateWebhookAddress::dispatch($wallet, 'remove');
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
