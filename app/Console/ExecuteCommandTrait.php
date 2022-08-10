<?php

namespace App\Console;

trait ExecuteCommandTrait
{
    protected function executeCommand($command)
    {
        $this->info("Running $command ...");
        $result = exec($command, $output, $result_code);
        do_log(sprintf('command: %s, result_code: %s, output: %s, result: %s', $command, $result_code, json_encode($output), $result));
        if ($result_code != 0) {
            throw new \RuntimeException(json_encode($output));
        }
    }
}
