<?php

namespace App\Actions\Users;

use App\Models\ImportRun;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

class ImportUsers
{
    /**
     * Memproses baris impor (heading sudah dinormalisasi) dan mencatat hasilnya di ImportRun.
     * Baris gagal tidak menghentikan baris lain.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function handle(ImportRun $run, array $rows): ImportRun
    {
        $validator = new UserRowValidator;
        $failed = [];
        $success = 0;

        foreach ($rows as $index => $row) {
            $line = $index + 2; // baris 1 = judul kolom

            $result = $validator->validate($row);

            if ($result['data'] === null) {
                $failed[] = ['row' => $line, 'errors' => $result['errors']];

                continue;
            }

            DB::transaction(function () use ($result) {
                $data = $result['data'];

                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'identity_number' => $data['identity_number'],
                    'password' => $data['password'],
                ]);
                $user->assignRole($data['roles']);
            });

            $success++;
        }

        $run->update([
            'status' => 'done',
            'total_rows' => count($rows),
            'success_rows' => $success,
            'failed_rows' => $failed,
        ]);

        Audit::record('users.imported', $run, ['success' => $success, 'failed' => count($failed)], null, $run->user_id);

        return $run;
    }
}
