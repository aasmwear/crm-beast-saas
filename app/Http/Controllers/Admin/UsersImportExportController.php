<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as FacadesResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class UsersImportExportController extends Controller
{
    public function export(): StreamedResponse
    {
        $rows = User::query()->orderBy('id')->get(['id', 'name', 'email']);
        $cb = static function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'name', 'email']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->id, $r->name, $r->email]);
            }
            fclose($out);
        };

        return FacadesResponse::streamDownload($cb, 'users.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request): RedirectResponse
    {
        $file = $request->file('file');
        if (! $file) {
            return back()->with('error', 'No file');
        }
        $fh = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($fh);
        while (($row = fgetcsv($fh)) !== false) {
            $rec = array_combine($header, $row);
            if (! $rec || empty($rec['email'])) {
                continue;
            }
            User::query()->updateOrCreate(['email' => $rec['email']], [
                'name' => $rec['name'] ?? 'User',
                'password' => \Hash::make('password'),
            ]);
        }
        fclose($fh);

        return back()->with('success', 'Imported');
    }
}
