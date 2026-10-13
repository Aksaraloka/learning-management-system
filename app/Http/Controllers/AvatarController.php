<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\FileResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AvatarController extends Controller
{
    /**
     * Foto profil: privat, hanya untuk pengguna yang sudah masuk (rute berada di grup auth).
     */
    public function __invoke(Request $request, User $user): StreamedResponse
    {
        abort_unless($user->hasAvatar(), 404);

        return FileResponse::serve($request, $user->avatar_path, 'foto-'.$user->id, $user->avatar_mime);
    }
}
