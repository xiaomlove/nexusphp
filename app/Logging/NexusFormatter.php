<?php
namespace App\Logging;

use Monolog\Formatter\LineFormatter;

class NexusFormatter
{
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter($this->formatter());
        }
    }

    protected function formatter()
    {
        $format = "[%datetime%] [" . REQUEST_ID . "] %channel%.%level_name%: %message% %context% %extra%\n";
        return tap(new LineFormatter($format, 'Y-m-d H:i:s', true, true), function ($formatter) {
            $formatter->includeStacktraces();
        });
    }
}
