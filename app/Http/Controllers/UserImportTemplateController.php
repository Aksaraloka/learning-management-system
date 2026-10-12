<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserImportTemplateController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        Gate::authorize('import', User::class);

        $rows = [
            ['name', 'identity_number', 'email', 'role', 'password'],
            ['Andi Pratama', '2026001', 'andi@contoh.sch.id', 'student', ''],
            ['Siti Rahma, S.Pd.', '198001012005', 'siti@contoh.sch.id', 'teacher|homeroom', ''],
        ];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            foreach ($rows as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, 'template-impor-pengguna.csv', ['Content-Type' => 'text/csv']);
    }
}
