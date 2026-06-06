<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = User::findOrFail($request->id);

        if (! hash_equals(
            (string) $request->hash,
            sha1($user->getEmailForVerification())
        )) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {

            $user->markEmailAsVerified();

            event(new Verified($user));
        }

        return redirect()
            ->route('login')
            ->with(
                'status',
                'Your email has been verified successfully. You may now log in.'
            );
    }
}
