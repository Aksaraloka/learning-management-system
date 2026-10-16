<?php

namespace App\Http\Controllers;

use App\Actions\Auth\AcademicSignInRejected;
use App\Actions\Auth\SignInFromAcademic;
use App\Support\Academic\AcademicException;
use App\Support\Academic\AcademicSso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AcademicAuthController extends Controller
{
    public function redirect(AcademicSso $sso): RedirectResponse
    {
        abort_unless(config('academic.enabled'), 404);

        return redirect()->away($sso->authorizationUrl());
    }

    public function callback(Request $request, AcademicSso $sso, SignInFromAcademic $signIn): RedirectResponse
    {
        abort_unless(config('academic.enabled'), 404);

        try {
            $signIn->handle($sso->profileFromCallback($request));
        } catch (AcademicException|AcademicSignInRejected $e) {
            return redirect()->route('login')->with('sso_error', $e->getMessage());
        }

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }
}
