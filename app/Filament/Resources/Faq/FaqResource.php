<?php

namespace App\Filament\Resources\Faq;

use App\Filament\Resources\Faq\FaqResource\Pages\CreateFaq;
use App\Filament\Resources\Faq\FaqResource\Pages\EditFaq;
use App\Filament\Resources\Faq\FaqResource\Pages\ListFaqs;
use App\Models\Faq;
use App\Models\Language;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Filament 5 admin resource for the `faq` table — replaces the
 * legacy `public/faqmanage.php` listing page and the
 * `public/faqactions.php` action dispatcher (both deleted in the
 * same PR). The legacy URLs `/faqmanage.php` and `/faqactions.php`
 * 302 to `/nexusphp/faqs` so admin bookmarks keep working — see
 * `routes/web.php`.
 *
 * The `faq` table is bimodal:
 *   - `type='categ'` rows are the section headers shown on the
 *     public FAQ page. They have a title (`question`), a language
 *     (`lang_id`), and a flag of either Hidden (0) or Normal (1).
 *     The `answer` and `categ` columns are unused (legacy stored
 *     the empty string and `0` respectively).
 *   - `type='item'` rows are the Q&A pairs that hang under a
 *     section. They have a `question`, a free-form HTML `answer`
 *     (the public side runs `format_comment()` over it — do NOT
 *     escape on save), a parent section reference via
 *     `categ → parent.link_id`, and the four-value flag (Hidden,
 *     Normal, Updated marker, New marker).
 *
 * The single Filament form below uses a `type` selector and
 * conditional component visibility to handle both shapes. Saving a
 * row triggers `App\Models\Faq::forgetPublicCache()` via the model's
 * `saved` event, which invalidates every `faq:body:*` cache key the
 * public `FaqController` reads through.
 */
class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static ?string $slug = 'faqs';

    protected static ?string $pluralModelLabel = 'FAQ entries';

    protected static ?string $label = 'FAQ entry';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 60;

    public static function getNavigationLabel(): string
    {
        return 'FAQ Management';
    }

    /**
     * Restrict the resource to administrators+. Mirrors the legacy
     * `if (get_user_class() < UC_ADMINISTRATOR) permissiondenied();`
     * gate at the top of `public/faqmanage.php` and the matching
     * `stderr('Error', 'Only Administrators...')` guard in
     * `public/faqactions.php`.
     */
    public static function canAccess(): bool
    {
        $user = Auth::guard('nexus-web')->user() ?? Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        return (int) $user->class >= (int) User::CLASS_ADMINISTRATOR;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->label('Type')
                ->options([
                    Faq::TYPE_CATEG => 'Section',
                    Faq::TYPE_ITEM => 'Item',
                ])
                ->required()
                ->live()
                // Type is fixed at create-time; legacy admin had no
                // "convert section to item" affordance, and changing
                // a section into an item silently orphans every child.
                ->disabledOn('edit'),

            Select::make('lang_id')
                ->label('Language')
                ->options(fn (): array => Language::query()
                    ->orderBy('lang_name')
                    ->pluck('lang_name', 'id')
                    ->toArray()
                )
                ->required()
                ->live(),

            // Section header / item question. Plain text input is
            // intentional — the legacy form used `<input type="text">`
            // for both branches and rendered the value through
            // `htmlspecialchars()` on the public side.
            TextInput::make('question')
                ->label(fn (callable $get): string => $get('type') === Faq::TYPE_CATEG ? 'Section title' : 'Question')
                ->required()
                ->maxLength(65535)
                ->columnSpanFull(),

            // Item-only: arbitrary HTML / BBCode, run through
            // `format_comment()` on the public side. We deliberately
            // do not escape on save so the legacy renderer keeps
            // emitting the same HTML it did before.
            Textarea::make('answer')
                ->label('Answer (HTML / BBCode allowed)')
                ->rows(20)
                ->visible(fn (callable $get): bool => $get('type') === Faq::TYPE_ITEM)
                ->dehydrateStateUsing(fn ($state): string => (string) ($state ?? ''))
                ->columnSpanFull(),

            // Item-only: parent section selector. The legacy form
            // wrote the parent section's `link_id` (not its primary
            // key) into `categ`; reproduced here so `Faq::categSection`
            // and `FaqController::renderBody` keep resolving items
            // back to their parent.
            Select::make('categ')
                ->label('Parent section')
                ->options(function (callable $get) {
                    $langId = (int) ($get('lang_id') ?? 0);
                    if ($langId <= 0) {
                        return [];
                    }

                    return Faq::query()
                        ->where('type', Faq::TYPE_CATEG)
                        ->where('lang_id', $langId)
                        ->orderBy('order')
                        ->pluck('question', 'link_id')
                        ->toArray();
                })
                ->required(fn (callable $get): bool => $get('type') === Faq::TYPE_ITEM)
                ->visible(fn (callable $get): bool => $get('type') === Faq::TYPE_ITEM),

            Select::make('flag')
                ->label('Status')
                ->options(fn (callable $get): array => $get('type') === Faq::TYPE_CATEG
                    ? Faq::FLAGS_CATEG
                    : Faq::FLAGS_ITEM
                )
                ->default(Faq::FLAG_NORMAL)
                ->required(),

            // Sections store `categ=0` and `answer=''` per the legacy
            // contract. Carry the values through the form so we don't
            // need a `mutateFormDataBeforeCreate` hook just for these
            // two no-ops.
            Hidden::make('categ')
                ->default(0)
                ->visible(fn (callable $get): bool => $get('type') === Faq::TYPE_CATEG)
                ->dehydrateStateUsing(fn (): int => 0),

            Hidden::make('answer')
                ->default('')
                ->visible(fn (callable $get): bool => $get('type') === Faq::TYPE_CATEG)
                ->dehydrateStateUsing(fn (): string => ''),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === Faq::TYPE_CATEG ? 'Section' : 'Item')
                    ->color(fn (string $state): string => $state === Faq::TYPE_CATEG ? 'primary' : 'gray')
                    ->sortable(),

                TextColumn::make('language.lang_name')
                    ->label('Language')
                    ->sortable(),

                TextColumn::make('question')
                    ->label('Title / Question')
                    ->limit(80)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('flag')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => Faq::FLAGS_ITEM[$state] ?? 'Normal')
                    ->color(fn (int $state): string => match ($state) {
                        Faq::FLAG_HIDDEN => 'danger',
                        Faq::FLAG_UPDATED => 'info',
                        Faq::FLAG_NEW => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options([
                    Faq::TYPE_CATEG => 'Section',
                    Faq::TYPE_ITEM => 'Item',
                ]),
                SelectFilter::make('lang_id')
                    ->label('Language')
                    ->options(fn (): array => Language::query()
                        ->orderBy('lang_name')
                        ->pluck('lang_name', 'id')
                        ->toArray()
                    ),
                SelectFilter::make('flag')->options(Faq::FLAGS_ITEM),
            ])
            ->defaultSort('id', 'desc')
            // Default group: by language → section. Mirrors the legacy
            // `faqmanage.php` listing which was implicitly grouped by
            // language because it iterated `$faq_categ[$lang][$id]`.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->orderBy('lang_id')
                ->orderBy('type')
                ->orderBy('order')
            )
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaqs::route('/'),
            'create' => CreateFaq::route('/create'),
            'edit' => EditFaq::route('/{record}/edit'),
        ];
    }
}
