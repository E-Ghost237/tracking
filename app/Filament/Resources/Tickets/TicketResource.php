<?php

namespace App\Filament\Resources\Tickets;

use App\Models\Ticket;
use App\Models\User;
use App\Services\Support\TicketService;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
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
use UnitEnum;

/**
 * Support tickets and contact messages (FR-107).
 */
class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|UnitEnum|null $navigationGroup = 'Customers and support';

    protected static ?string $navigationLabel = 'Support';

    protected static ?int $navigationSort = 20;

    public static function getNavigationBadge(): ?string
    {
        $count = Ticket::query()->where('status', 'open')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()->columnSpan(1)->schema([
                TextEntry::make('subject')->weight('bold'),
                TextEntry::make('status')->badge(),
                TextEntry::make('source')->badge(),
                TextEntry::make('name'),
                TextEntry::make('email')->copyable(),
                TextEntry::make('phone')->placeholder('—'),
                TextEntry::make('shipment.tracking_number')->label('Shipment')->placeholder('—'),
                TextEntry::make('assignee.name')->label('Assigned to')->placeholder('Unassigned'),
            ]),
            Section::make('Conversation')->columnSpan(2)->schema([
                RepeatableEntry::make('messages')->label('')->schema([
                    TextEntry::make('author')->label('')->weight('bold')
                        ->state(fn ($record) => ($record->is_staff ? 'Staff · ' : 'Customer · ').($record->user?->name ?? '').' · '.$record->created_at?->toDayDateTimeString()),
                    TextEntry::make('body')->label('')->prose(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')->searchable()->weight('bold')->limit(60),
                TextColumn::make('email')->searchable(),
                TextColumn::make('source')->badge(),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'open' => 'warning',
                    'answered' => 'info',
                    default => 'gray',
                }),
                TextColumn::make('assignee.name')->label('Assigned')->placeholder('—'),
                TextColumn::make('last_reply_at')->since()->sortable(),
            ])
            ->defaultSort('last_reply_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(Ticket::STATUSES)->default('open'),
                SelectFilter::make('source')->options(['account' => 'Account', 'contact' => 'Contact form']),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function replyAction(): Action
    {
        return Action::make('reply')->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->visible(fn () => auth()->user()->hasPermission(Permissions::TICKETS_MANAGE))
            ->schema([
                Textarea::make('message')->required()->rows(6)->maxLength(5000),
                Toggle::make('close')->label('Close the ticket after replying'),
            ])
            ->action(function (Ticket $record, array $data, TicketService $tickets): void {
                $tickets->staffReply($record, auth()->user(), $data['message'], (bool) $data['close']);
                Notification::make()->success()->title('Reply sent by email.')->send();
            });
    }

    public static function assignAction(): Action
    {
        return Action::make('assign')->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn () => auth()->user()->hasPermission(Permissions::TICKETS_MANAGE))
            ->schema([
                Select::make('assigned_to')->label('Staff member')
                    ->options(fn () => User::query()->whereHas('roles', fn ($q) => $q->where('is_staff', true))->pluck('name', 'id')->all()),
                Select::make('status')->options(Ticket::STATUSES),
            ])
            ->fillForm(fn (Ticket $record) => ['assigned_to' => $record->assigned_to, 'status' => $record->status])
            ->action(fn (Ticket $record, array $data) => $record->update(array_filter($data, fn ($v) => $v !== null)));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'view' => Pages\ViewTicket::route('/{record}'),
        ];
    }
}
