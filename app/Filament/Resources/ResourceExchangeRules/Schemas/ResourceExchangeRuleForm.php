<?php

namespace App\Filament\Resources\ResourceExchangeRules\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ResourceExchangeRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('min_size_gb')->label('最小资源大小（GB）')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('max_size_gb')->label('最大资源大小（GB）')
                    ->numeric(),
                TextInput::make('bonus_per_gb_day')->label('每GB每天魔力值')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('tier1_bonus_per_day')->label('一级置顶每天魔力值')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('tier2_bonus_per_day')->label('二级置顶每天魔力值')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('tier3_bonus_per_day')->label('三级置顶每天魔力值')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('enabled')->label('启用规则')
                    ->required(),
            ]);
    }
}
