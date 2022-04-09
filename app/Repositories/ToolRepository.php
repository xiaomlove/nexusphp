<?php
namespace App\Repositories;

use App\Models\Message;
use App\Models\News;
use App\Models\Poll;
use App\Models\PollAnswer;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class ToolRepository extends BaseRepository
{
    public function backupWeb(): array
    {
        $webRoot = base_path();
        $dirName = basename($webRoot);
        $filename = sprintf('%s/%s.web.%s.tar.gz', sys_get_temp_dir(), $dirName, date('Ymd.His'));
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

    public function backupAll(): array
    {
        $backupWeb = $this->backupWeb();
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

        $localAdapter = new \League\Flysystem\Local\LocalFilesystemAdapter('/');
        $localFilesystem = new \League\Flysystem\Filesystem($localAdapter);

        $filename = $backupResult['filename'];
        $start = Carbon::now();
        try {
            $filesystem->writeStream(basename($filename), $localFilesystem->readStream($filename));
            $speed = !(float)$start->diffInSeconds() ? 0 :filesize($filename) / (float)$start->diffInSeconds();
            $log =  'Elapsed time: '.$start->diffForHumans(null, true);
            $log .= ', Speed: '. number_format($speed/1024,2) . ' KB/s';
            do_log($log);
            $backupResult['upload_result'] = 'success: '  .$log;
        } catch (\Throwable $exception) {
            $backupResult['upload_result'] = 'fail: ' . $exception->getMessage();
        }
        return $backupResult;
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
            $smtp['smtpport'] ?? null
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
