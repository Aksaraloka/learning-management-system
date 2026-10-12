<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function destroy(Request $request): RedirectResponse
    {
        auth()->guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /**
     * Menyimpan pilihan tema ke akun agar ikut di perangkat lain.
     */
    public function theme(Request $request): JsonResponse
    {
        $data = $request->validate(['theme' => ['required', 'in:light,dark,system']]);

        $request->user()->forceFill($data)->save();

        return response()->json(['theme' => $data['theme']]);
    }
}
