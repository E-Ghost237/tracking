<?php

namespace App\Filament\Pages;

use App\Services\AuditLogger;
use App\Services\Settings as SettingsStore;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Platform settings (FR-129): currency, exchange rates, quote validity, payment expiry,
 * review threshold, maintenance mode. Every change is audited.
 *
 * @property-read Schema $form
 */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    private const KEYS = [
        'currency', 'exchange_rates', 'quote_validity_days', 'quote_range_percent', 'payment_expiry_hours', 'payment_reminder_hours',
        'review_target_minutes', 'review_alert_minutes', 'two_person_threshold', 'max_rejected_attempts', 'road_max_km',
        'minimum_charge', 'payment_details_change_delay_hours', 'gift_card_min_account_age_days', 'maintenance_mode', 'staffed_hours',
        'hero_scene_default',
    ];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::SETTINGS_MANAGE);
    }

    public function mount(SettingsStore $settings): void
    {
        $values = [];
        foreach (self::KEYS as $key) {
            $values[$key] = $settings->get($key);
        }
        $values['payment_reminder_hours'] = array_map('strval', (array) ($values['payment_reminder_hours'] ?? []));
        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Money')->columns(3)->schema([
                Select::make('currency')->options(['USD' => 'USD'])->required()->disabled()->dehydrated(),
                TextInput::make('minimum_charge')->label('Minimum charge (cents)')->numeric()->required()->minValue(0),
                TextInput::make('two_person_threshold')->label('Two-person approval from (cents)')->numeric()->required()->minValue(0),
                KeyValue::make('exchange_rates')->label('Exchange rates (1 USD = …)')->keyLabel('Currency')->valueLabel('Rate')->columnSpanFull(),
            ]),
            Section::make('Quotes and payments')->columns(3)->schema([
                TextInput::make('quote_validity_days')->numeric()->required()->minValue(1)->maxValue(60),
                TextInput::make('quote_range_percent')->label('Price range shown (%)')->numeric()->required()->minValue(0)->maxValue(50),
                TextInput::make('payment_expiry_hours')->numeric()->required()->minValue(1)->maxValue(168),
                TagsInput::make('payment_reminder_hours')->label('Reminders (hours before expiry)'),
                TextInput::make('max_rejected_attempts')->numeric()->required()->minValue(1)->maxValue(20),
                TextInput::make('payment_details_change_delay_hours')->label('Delay before changed payment details go live (hours)')->numeric()->required()->minValue(0)->maxValue(72),
                TextInput::make('gift_card_min_account_age_days')->numeric()->required()->minValue(0),
            ]),
            Section::make('Review and operations')->columns(3)->schema([
                TextInput::make('review_target_minutes')->numeric()->required()->minValue(5),
                TextInput::make('review_alert_minutes')->numeric()->required()->minValue(5),
                TextInput::make('staffed_hours')->maxLength(120),
                TextInput::make('road_max_km')->label('Road range (km)')->numeric()->required()->minValue(100),
                Select::make('hero_scene_default')->options(['air' => 'Air', 'sea' => 'Sea', 'road' => 'Road']),
                Toggle::make('maintenance_mode')->helperText('The public site shows a maintenance page; staff can still sign in.'),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('save')->label('Save settings')->action('save')];
    }

    public function save(SettingsStore $settings, AuditLogger $audit): void
    {
        $data = $this->form->getState();
        $data['exchange_rates'] = collect((array) ($data['exchange_rates'] ?? []))
            ->mapWithKeys(fn ($rate, $code) => [strtoupper(substr((string) $code, 0, 3)) => (float) $rate])
            ->filter(fn ($rate) => $rate > 0)->put('USD', 1.0)->all();
        $data['payment_reminder_hours'] = array_values(array_filter(array_map('intval', (array) ($data['payment_reminder_hours'] ?? [])), fn ($h) => $h > 0 && $h < 168));

        foreach (self::KEYS as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $value = is_numeric($data[$key]) && ! is_string($data[$key]) ? $data[$key] : $data[$key];
            if (in_array($key, ['quote_validity_days', 'quote_range_percent', 'payment_expiry_hours', 'review_target_minutes', 'review_alert_minutes', 'two_person_threshold', 'max_rejected_attempts', 'road_max_km', 'minimum_charge', 'payment_details_change_delay_hours', 'gift_card_min_account_age_days'], true)) {
                $value = (int) $value;
            }
            if ($key === 'maintenance_mode') {
                $value = (bool) $value;
            }
            $change = $settings->set($key, $value);
            if ($change['before'] != $change['after']) {
                $audit->log('settings.updated', 'Setting', ['key' => $key, 'value' => $change['before']], ['key' => $key, 'value' => $change['after']]);
            }
        }

        Notification::make()->success()->title('Settings saved.')->send();
    }
}
