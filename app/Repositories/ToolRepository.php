<?php
namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ToolRepository extends BaseRepository
{
    public function getSystemInfo()
    {
        $systemInfo = [
            'nexus_version' => config('app.nexus_version'),
            'laravel_version' => \Illuminate\Foundation\Application::VERSION,
            'php_version' => PHP_VERSION,
            'mysql_version' => DB::select(DB::raw('select version() as info'))[0]->info,
            'os' => PHP_OS,
            'server_software' => $_SERVER['SERVER_SOFTWARE'],

        ];

        return $systemInfo;
    }

    public function backupWebRoot()
    {
        $webRoot = base_path();
        $dirName = basename($webRoot);
        $filename = sprintf('%s/%s.%s.tar.gz', sys_get_temp_dir(), $dirName, date('Ymd.His'));
        $command = sprintf(
            'tar --exclude=vendor --exclude=.git -czf %s -C %s %s',
            $filename, dirname($webRoot), $dirName
        );
        $result = exec($command, $output, $result_code);
        do_log(sprintf(
            "command: %s, output: %s, result_code: %s, result: %s, filename: %s",
            $command, json_encode($output), $result_code, $result, $filename
        ));
        return compact('result_code', 'filename');
    }

    public function backupDatabase()
    {
        $connectionName = config('database.default');
        $config = config("database.connections.$connectionName");
        $filename = sprintf('%s/%s.database.%s.sql', sys_get_temp_dir(), basename(base_path()), date('Ymd.His'));
        $command = sprintf(
            'mysqldump --user=%s --password=%s --port=%s --single-transaction --databases %s >> %s',
            $config['username'], $config['password'], $config['port'], $config['database'], $filename,
        );
        $result = exec($command, $output, $result_code);
        do_log(sprintf(
            "command: %s, output: %s, result_code: %s, result: %s, filename: %s",
            $command, json_encode($output), $result_code, $result, $filename
        ));
        return compact('result_code', 'filename');
    }

    public function backupAll($uploadToGoogleDrive = false)
    {
        $backupWeb = $this->backupWebRoot();
        if ($backupWeb['result_code'] != 0) {
            throw new \RuntimeException("backup web fail: " . json_encode($backupWeb));
        }
        $backupDatabase = $this->backupDatabase();
        if ($backupDatabase['result_code'] != 0) {
            throw new \RuntimeException("backup database fail: " . json_encode($backupDatabase));
        }
        $filename = sprintf('%s/%s.%s.tar.gz', sys_get_temp_dir(), basename(base_path()), date('Ymd.His'));
        $command = sprintf(
            'tar -czf %s -C %s %s -C %s %s',
            $filename,
            dirname($backupWeb['filename']), basename($backupWeb['filename']),
            dirname($backupDatabase['filename']), basename($backupDatabase['filename'])
        );
        $result = exec($command, $output, $result_code);
        do_log(sprintf(
            "command: %s, output: %s, result_code: %s, result: %s, filename: %s",
            $command, json_encode($output), $result_code, $result, $filename
        ));
        $upload_result = '';
        if ($uploadToGoogleDrive) {
            $disk = Storage::disk('google_drive');
            $upload_result = $disk->put(basename($filename), fopen($filename, 'r'));
        }
        return compact('result_code', 'filename', 'upload_result');

    }
}
