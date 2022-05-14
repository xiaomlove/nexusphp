<?php
namespace App\Repositories;

use App\Models\Message;
use App\Models\News;
use App\Models\Poll;
use App\Models\PollAnswer;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class ToolRepository extends BaseRepository
{
    public function backupWeb($method = null): array
    {
        $webRoot = base_path();
        $dirName = basename($webRoot);
        $excludes = ['vendor', 'node_modules', '.git', '.idea', '.settings', '.DS_Store'];
        $baseFilename = sprintf('%s/%s.web.%s', sys_get_temp_dir(), $dirName, date('Ymd.His'));
        if (command_exists('tar') && ($method === 'tar' || $method === null)) {
            $filename = $baseFilename . ".tar.gz";
            $command = "tar";
            foreach ($excludes as $item) {
                $command .= " --exclude=$item";
            }
            $command .= sprintf(
                ' -czf %s -C %s %s',
                $filename, dirname($webRoot), $dirName
            );
            $result = exec($command, $output, $result_code);
            do_log(sprintf(
                "command: %s, output: %s, result_code: %s, result: %s, filename: %s",
                $command, json_encode($output), $result_code, $result, $filename
            ));
        } else {
            //use php zip
            $filename = $baseFilename . ".zip";
            $zip = new \ZipArchive();
            $zipOpen = $zip->open($filename, \ZipArchive::CREATE);
            if ($zipOpen !== true) {
                throw new \RuntimeException("Can not open $filename, error: $zipOpen");
            }
            // create recursive directory iterator
            $files = new \RecursiveIteratorIterator (new \RecursiveDirectoryIterator($webRoot, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY);
            // let's iterate
            foreach ($files as $name => $file) {
                $localeName = substr($name, strlen($webRoot) + 1);
                $start = strstr($localeName, DIRECTORY_SEPARATOR, true) ?: $localeName;
                //add a directory
                $localeName = $dirName . DIRECTORY_SEPARATOR . $localeName;
                if (!in_array($start, $excludes)) {
                    if (is_file($name)) {
                        $zip->addFile($name, $localeName);
                    } elseif (is_dir($name)) {
                        do_log("Is dir: $name.");
                        $zip->addEmptyDir($localeName);
                    } else {
                        do_log("Not file or dir $name.", 'error');
                    }
                }
            }
            $zip->close();
            $result_code = 0;
            do_log("No tar command, use zip.");
        }
        return compact('result_code', 'filename');
    }

    public function backupDatabase(): array
    {
        $connectionName = config('database.default');
        $config = config("database.connections.$connectionName");
        $filename = sprintf('%s/%s.database.%s.sql', sys_get_temp_dir(), basename(base_path()), date('Ymd.His'));
        $command = sprintf(
            'mysqldump --user=%s --password=%s --port=%s --single-transaction --no-create-db --databases %s >> %s',
            $config['username'], $config['password'], $config['port'], $config['database'], $filename,
        );
        $result = exec($command, $output, $result_code);
        do_log(sprintf(
            "command: %s, output: %s, result_code: %s, result: %s, filename: %s",
            $command, json_encode($output), $result_code, $result, $filename
        ));
        return compact('result_code', 'filename');
    }

    public function backupAll($method = null): array
    {
        $backupWeb = $this->backupWeb($method);
        if ($backupWeb['result_code'] != 0) {
            throw new \RuntimeException("backup web fail: " . json_encode($backupWeb));
        }
        $backupDatabase = $this->backupDatabase();
        if ($backupDatabase['result_code'] != 0) {
            throw new \RuntimeException("backup database fail: " . json_encode($backupDatabase));
        }
        $baseFilename = sprintf('%s/%s.%s', sys_get_temp_dir(), basename(base_path()), date('Ymd.His'));
        if (command_exists('tar') && ($method === 'tar' || $method === null)) {
            $filename = $baseFilename . ".tar.gz";
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
        } else {
            //use php zip
            $filename = $baseFilename . ".zip";
            $zip = new \ZipArchive();
            $zipOpen = $zip->open($filename, \ZipArchive::CREATE);
            if ($zipOpen !== true) {
                throw new \RuntimeException("Can not open $filename, error: $zipOpen");
            }
            $zip->addFile($backupWeb['filename'], basename($backupWeb['filename']));
            $zip->addFile($backupDatabase['filename'], basename($backupDatabase['filename']));
            $zip->close();
            $result_code = 0;
            do_log("No tar command, use zip.");
        }
        return compact('result_code', 'filename');

    }

    /**
     * do backup cronjob
     *
     * @return array|false
     */
    public function cronjobBackup($force = false): bool|array
    {
        $setting = Setting::get('backup');
        if ($setting['enabled'] != 'yes' && !$force) {
            do_log("Backup not enabled.");
            return false;
        }
        $now = now();
        $frequency = $setting['frequency'];
        $settingHour = (int)$setting['hour'];
        $settingMinute = (int)$setting['minute'];
        $nowHour = (int)$now->format('H');
        $nowMinute = (int)$now->format('i');
        do_log("Backup frequency: $frequency, force: " . strval($force));
        if (!$force) {
            if ($frequency == 'daily') {
                if ($settingHour != $nowHour) {
                    do_log(sprintf('Backup setting hour: %s != now hour: %s', $settingHour, $nowHour));
                    return false;
                }
                if ($settingMinute != $nowMinute) {
                    do_log(sprintf('Backup setting minute: %s != now minute: %s', $settingMinute, $nowMinute));
                    return false;
                }
            } elseif ($frequency == 'hourly') {
                if ($settingMinute != $nowMinute) {
                    do_log(sprintf('Backup setting minute: %s != now minute: %s', $settingMinute, $nowMinute));
                    return false;
                }
            } else {
                throw new \RuntimeException("Unknown backup frequency: $frequency");
            }
        }
        $backupResult = $this->backupAll();
        do_log("Backup all result: " . json_encode($backupResult));
        if ($backupResult['result_code'] != 0) {
            throw new \RuntimeException("Backup all fail.");
        }
        $filename = $backupResult['filename'];

        $saveResult = $this->saveToGoogleDrive($setting, $filename);
        do_log("[BACKUP_GOOGLE_DRIVE]: $saveResult");
        $backupResult['google_drive'] = $saveResult;

        $saveResult = $this->saveToFtp($setting, $filename);
        do_log("[BACKUP_FTP]: $saveResult");
        $backupResult['ftp'] = $saveResult;

        $saveResult = $this->saveToSftp($setting, $filename);
        do_log("[BACKUP_SFTP]: $saveResult");
        $backupResult['sftp'] = $saveResult;

        do_log("[BACKUP_ALL_DONE]: " . json_encode($backupResult));

        return $backupResult;

    }

    private function saveToGoogleDrive(array $setting, $filename): bool|string
    {
        $clientId = $setting['google_drive_client_id'] ?? '';
        $clientSecret = $setting['google_drive_client_secret'] ?? '';
        $refreshToken = $setting['google_drive_refresh_token'] ?? '';
        $folderId = $setting['google_drive_folder_id'] ?? '';

        if (empty($clientId)) {
            do_log("No google_drive_client_id, won't do upload.");
            return false;
        }
        if (empty($clientSecret)) {
            do_log("No google_drive_client_secret, won't do upload.");
            return false;
        }
        if (empty($refreshToken)) {
            do_log("No google_drive_refresh_token, won't do upload.");
            return false;
        }
        do_log("Google drive info: clientId: $clientId, clientSecret: $clientSecret, refreshToken: $refreshToken, folderId: $folderId");

        $client = new \Google\Client();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->refreshToken($refreshToken);
        $service = new \Google\Service\Drive($client);
        $adapter = new \Masbug\Flysystem\GoogleDriveAdapter($service, $folderId);
        $filesystem = new \League\Flysystem\Filesystem($adapter);
        $disk = new \Illuminate\Filesystem\FilesystemAdapter($filesystem, $adapter);
        return $this->doTransfer($disk, $filename);
    }

    private function saveToFtp(array $setting, $filename): bool|string
    {
        if ($setting['via_ftp'] !== 'yes') {
            do_log("via_ftp !== 'yes', via_ftp: " . $setting['via_ftp'] ?? '');
            return false;
        }
        $config = config('filesystems.disks.ftp');
        if (empty($config)) {
            do_log("No ftp config.");
            return false;
        }
        foreach (['host', 'username', 'password', 'root'] as $item) {
            if (empty($config[$item])) {
                do_log("No ftp $item.");
                return false;
            }
        }
        $disk = Storage::disk('ftp');
        return $this->doTransfer($disk, $filename);

    }

    public function saveToSftp(array $setting, $filename): bool|string
    {
        if ($setting['via_sftp'] !== 'yes') {
            do_log("via_sftp !== 'yes', via_sftp: " . $setting['via_sftp'] ?? '');
            return false;
        }
        $config = config('filesystems.disks.sftp');
        if (empty($config)) {
            do_log("No sftp config.");
            return false;
        }
        foreach (['host', 'username', 'password', 'root'] as $item) {
            if (empty($config[$item])) {
                do_log("No sftp $item.");
                return false;
            }
        }
        $disk = Storage::disk('sftp');
        return $this->doTransfer($disk, $filename);
    }

    private function doTransfer(\Illuminate\Filesystem\FilesystemAdapter $remoteFilesystem, $filename): bool|string
    {
        $localAdapter = new \League\Flysystem\Local\LocalFilesystemAdapter('/');
        $localFilesystem = new \League\Flysystem\Filesystem($localAdapter);
        $start = Carbon::now();
        try {
            $remoteFilesystem->writeStream(basename($filename), $localFilesystem->readStream($filename));
            $speed = !(float)$start->diffInSeconds() ? 0 :filesize($filename) / (float)$start->diffInSeconds();
            $log =  'Elapsed time: '.$start->diffForHumans(null, true);
            $log .= ', Speed: '. number_format($speed/1024,2) . ' KB/s';
            do_log($log);
            return true;
        } catch (\Throwable $exception) {
            do_log("Transfer error: " . $exception->getMessage(), 'error');
            return $exception->getMessage();
        }
    }

    /**
     * @param $to
     * @param $subject
     * @param $body
     * @return bool
     */
    public function sendMail($to, $subject, $body): bool
    {
        $log = "[SEND_MAIL]";
        do_log("$log, to: $to, subject: $subject, body: $body");
        $factory = new EsmtpTransportFactory();
        $smtp = Setting::getFromDb('smtp');
        $encryption = null;
        if (isset($smtp['encryption']) && in_array($smtp['encryption'], ['ssl', 'tls'])) {
            $encryption = $smtp['encryption'];
        }
        // Create the Transport
        $transport = $factory->create(new Dsn(
            $encryption === 'tls' ? (($smtp['smtpport'] == 465) ? 'smtps' : 'smtp') : '',
            $smtp['smtpaddress'],
            $smtp['accountname'] ?? null,
            $smtp['accountpassword'] ?? null,
            $smtp['smtpport'] ?? null,
            ['verify_peer' => false]
        ));

        // Create the Mailer using your created Transport
        $mailer = new Mailer($transport);

        // Create a message
        $message = (new Email())
            ->from(new Address($smtp['accountname'], Setting::get('basic.SITENAME')))
            ->to($to)
            ->subject($subject)
            ->html($body)
        ;

        // Send the message
        try {
            $mailer->send($message);
            return true;
        } catch (\Throwable $e) {
            do_log("$log, fail: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'error');
            return false;
        }
    }

    public function getNotificationCount(User $user): array
    {
        $result = [];
        //attend or not
        $attendRep = new AttendanceRepository();
        $attendance = $attendRep->getAttendance($user->id, date('Ymd'));
        $result['attendance'] = $attendance ? 0 : 1;

        //unread news
        $count = News::query()->where('added', '>', $user->last_home)->count();
        $result['news'] = $count;

        //unread messages
        $count = Message::query()->where('receiver', $user->id)->where('unread', 'yes')->count();
        $result['message'] = $count;

        //un-vote poll
        $total = Poll::query()->count();
        $userVoteCount = PollAnswer::query()->where('userid', $user->id)->selectRaw('count(distinct(pollid)) as counts')->first()->counts;
        $result['poll'] = $total - $userVoteCount;

        return $result;
    }


}
