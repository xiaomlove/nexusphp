<?php

namespace App\Filament;

trait OptionsTrait
{
    private static array $matchTypes = ['dec' => 'dec', 'hex' => 'hex'];

    private static array $yesOrNo = ['yes' => 'yes', 'no' => 'no'];

    private static function getEnableDisableOptions($enableValue = 0, $disableValue = 1): array
    {
        return [
            $enableValue => __('label.enabled'),
            $disableValue => __('label.disabled'),
        ];
    }

    private static function getYesNoOptions($yesValue = 1, $noValue = 0): array
    {
        return [
            $yesValue => 'Yes',
            $noValue => 'No',
        ];
    }
}
