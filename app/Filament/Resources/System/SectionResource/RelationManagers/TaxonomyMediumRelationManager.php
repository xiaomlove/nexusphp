<?php

namespace App\Filament\Resources\System\SectionResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TaxonomyMediumRelationManager extends TaxonomySourcesRelationManager
{
    protected static string $relationship = 'taxonomy_medium';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $torrentField = 'medium';

}
