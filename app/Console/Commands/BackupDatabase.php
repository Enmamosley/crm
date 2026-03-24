<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup';
    protected $description = 'Create a database backup (MySQL dump or SQLite copy)';

    public function handle(): int
    {
        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);

        // Keep max 10 backups
        $backups = collect(File::files($backupDir))
            ->sortByDesc(fn($f) => $f->getMTime());

        if ($backups->count() >= 10) {
            foreach ($backups->slice(9) as $old) {
                File::delete($old->getPathname());
            }
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return $this->backupMysql($backupDir);
        }

        return $this->backupSqlite($backupDir);
    }

    private function backupMysql(string $backupDir): int
    {
        $host     = config('database.connections.mysql.host');
        $port     = config('database.connections.mysql.port', 3306);
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        $filename = $backupDir . '/backup_' . now()->format('Y-m-d_His') . '.sql.gz';

        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --routines %s | gzip > %s',
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($filename)
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->error('mysqldump failed. Make sure mysql-client is installed.');
            return self::FAILURE;
        }

        $this->info('Backup created: ' . basename($filename));
        return self::SUCCESS;
    }

    private function backupSqlite(string $backupDir): int
    {
        $dbPath = database_path('database.sqlite');

        if (!File::exists($dbPath)) {
            $this->error('SQLite database file not found.');
            return self::FAILURE;
        }

        $filename = $backupDir . '/backup_' . now()->format('Y-m-d_His') . '.sqlite';
        File::copy($dbPath, $filename);

        $this->info('Backup created: ' . basename($filename));
        return self::SUCCESS;
    }
}
