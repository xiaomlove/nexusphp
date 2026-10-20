<?php

namespace App\Filament\Resources\ResourceExchangeRules;

use App\Filament\Resources\ResourceExchangeRules\Pages\CreateResourceExchangeRule;
use App\Filament\Resources\ResourceExchangeRules\Pages\EditResourceExchangeRule;
use App\Filament\Resources\ResourceExchangeRules\Pages\ListResourceExchangeRules;
use App\Filament\Resources\ResourceExchangeRules\Schemas\ResourceExchangeRuleForm;
use App\Filament\Resources\ResourceExchangeRules\Tables\ResourceExchangeRulesTable;
use App\Models\ResourceExchangeRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ResourceExchangeRuleResource extends Resource
{
    protected static ?string $model = ResourceExchangeRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ResourceExchangeRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResourceExchangeRulesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListResourceExchangeRules::route('/'),
            'create' => CreateResourceExchangeRule::route('/create'),
            'edit' => EditResourceExchangeRule::route('/{record}/edit'),
        ];
    }
}
