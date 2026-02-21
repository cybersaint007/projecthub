<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Throwable;

class ForgotPasswordController extends Controller
{
    /**
     * Show the forgot password form.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Send a reset link to the given email.
     * Always returns the same message to avoid account enumeration.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = $request->input('email');

        try {
            $status = Password::sendResetLink($request->only('email'));

            if ($status !== Password::RESET_LINK_SENT) {
                Log::info('Password reset: no email sent.', [
                    'reason' => $status,
                    'email' => $email,
                ]);
            }
        } catch (Throwable $e) {
            Log::error('Password reset link send failed: '.$e->getMessage(), [
                'email' => $email,
                'exception' => $e,
            ]);
        }

        return back()->with('status', '如果該 Email 存在，我們已寄出重設密碼連結。');
    }
}
