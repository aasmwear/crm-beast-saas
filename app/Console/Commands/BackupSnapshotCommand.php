<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackupSnapshotCommand extends Command
{
    protected $signature = 'backup:snapshot';

    protected $description = 'Create a simple DB snapshot (best-effort)';

    public function handle(): int
    {
        $conn = config('database.connections.pgsql');
        $db = $conn['database'] ?? 'postgres';
        $host = $conn['host'] ?? 'localhost';
        $user = $conn['username'] ?? 'postgres';

        $filename = 'backup/snapshot-'.now()->format('Ymd-His').'.sql';
        @Storage::disk('local')->makeDirectory('backup');

        $cmd = sprintf('PGPASSWORD="%s" pg_dump -h %s -U %s %s', $conn['password'] ?? '', $host, $user, $db);
        $output = @shell_exec(($cmd .= ' 2>&1'));
        if ($output) {
            Storage::disk('local')->put($filename, $output);
            $this->info('Snapshot saved to storage/app/'.$filename);

            return self::SUCCESS;
        }
        $this->warn('pg_dump unavailable or failed');

        return self::FAILURE;
    }
}
