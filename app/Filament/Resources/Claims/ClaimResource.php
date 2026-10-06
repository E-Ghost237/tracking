<?php

namespace App\Filament\Resources\Claims;

use App\Exceptions\DomainRuleException;
use App\Models\Claim;
use App\Models\StoredFile;
use App\Services\Files\FileStorageService;
use App\Services\Support\ClaimService;
use App\Support\Money;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Claims for lost, damaged or delayed shipments with a decision log (FR-108).
 */
class ClaimResource extends Resource
{
    protected static ?string $model = Claim::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Customers and support';

    protected static ?int $navigationSort = 30;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()->columnSpan(2)->columns(2)->schema([
                TextEntry::make('shipment.tracking_number')->label('Shipment'),
                TextEntry::make('type')->badge(),
                TextEntry::make('status')->badge(),
                TextEntry::make('amount_claimed')->formatStateUsing(fn (Claim $record) => Money::format($record->amount_claimed, $record->currency, 'en')),
                TextEntry::make('description')->columnSpanFull()->prose(),
                TextEntry::make('decision')->columnSpanFull()->placeholder('No decision yet'),
                TextEntry::make('attachments')->label('Evidence')->columnSpanFull()->html()->state(function (Claim $record): HtmlString {
                    $files = app(FileStorageService::class);
                    $links = StoredFile::query()->whereIn('public_id', $record->attachments ?? [])->get()
                        ->map(fn (StoredFile $f) => $f->scan_status === 'infected'
                            ? '<span class="text-danger-600">'.e($f->original_name).' (removed: failed scan)</span>'
                            : '<a class="text-primary-600 underline" target="_blank" rel="noopener" href="'.e($files->temporaryUrl($f)).'">'.e($f->original_name).'</a>');

                    return new HtmlString($links->isEmpty() ? 'None' : $links->implode(' · '));
                }),
            ]),
            Section::make('Decision log')->columnSpan(1)->schema([
                RepeatableEntry::make('logs')->label('')->schema([
                    TextEntry::make('action')->label('')->state(fn ($record) => $record->action.' · '.$record->created_at?->toFormattedDateString()),
                    TextEntry::make('note')->label('')->placeholder(''),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('shipment.tracking_number')->label('Shipment')->fontFamily('mono')->searchable(),
                TextColumn::make('user.email')->label('Customer')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('amount_claimed')->formatStateUsing(fn (Claim $record) => Money::format($record->amount_claimed, $record->currency, 'en')),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([SelectFilter::make('status')->options(Claim::STATUSES)])
            ->recordActions([ViewAction::make()]);
    }

    public static function decideAction(): Action
    {
        return Action::make('decide')->label('Update / decide')->icon(Heroicon::OutlinedScale)
            ->visible(fn () => auth()->user()->hasPermission(Permissions::CLAIMS_MANAGE))
            ->schema([
                Select::make('status')->options(['under_review' => 'Under review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'paid' => 'Paid'])->required(),
                Textarea::make('decision')->label('Message to the customer')->required()->maxLength(2000),
                TextInput::make('amount_approved')->label('Amount approved (USD)')->numeric()->minValue(0),
            ])
            ->action(function (Claim $record, array $data, ClaimService $claims): void {
                try {
                    $amount = isset($data['amount_approved']) && $data['amount_approved'] !== '' ? Money::fromMajor($data['amount_approved']) : null;
                    $claims->decide($record, auth()->user(), $data['status'], $data['decision'], $amount);
                    Notification::make()->success()->title('Claim updated and customer notified.')->send();
                } catch (DomainRuleException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClaims::route('/'),
            'view' => Pages\ViewClaim::route('/{record}'),
        ];
    }
}
