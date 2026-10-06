<?php

namespace App\Filament\Pages;

use App\Enums\ShipmentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Shipment;
use App\Services\Shipping\ShipmentEventService;
use App\Support\Permissions;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Throwable;
use UnitEnum;

/**
 * Bulk tracking events from CSV (number, status, place, time) with a preview and an error
 * report before anything is saved (FR-122).
 *
 * @property-read Schema $form
 */
class ImportEvents extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Import events';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.import-events';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $rows = [];

    /**
     * @var array<int, string>
     */
    public array $importErrors = [];

    private const MAX_ROWS = 2000;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::EVENTS_IMPORT);
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            FileUpload::make('file')->label('CSV file with columns: number, status, place, time (ISO 8601, UTC)')
                ->disk('local')->directory('imports')->visibility('private')
                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])->maxSize(2048)->required(),
        ]);
    }

    public function preview(): void
    {
        $state = $this->form->getState();
        $path = Storage::disk('local')->path($state['file']);
        [$this->rows, $this->importErrors] = $this->parse($path);
        Storage::disk('local')->delete($state['file']);
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function parse(string $path): array
    {
        $rows = [];
        $errors = [];
        $handle = fopen($path, 'r');
        $header = array_map(fn ($h) => strtolower(trim((string) $h, " \t\n\r\0\x0B\xEF\xBB\xBF")), (array) fgetcsv($handle, escape: ''));
        if ($header !== ['number', 'status', 'place', 'time']) {
            fclose($handle);

            return [[], ['The header must be: number,status,place,time']];
        }

        $line = 1;
        while (($row = fgetcsv($handle, escape: '')) !== false && count($rows) < self::MAX_ROWS) {
            $line++;
            if ($row === [null]) {
                continue;
            }
            if (count($row) !== 4) {
                $errors[] = "Line $line: expected 4 columns.";

                continue;
            }
            [$number, $status, $place, $time] = array_map('trim', $row);
            $shipment = Shipment::query()->where('tracking_number', strtoupper($number))->whereNotNull('released_at')->first();
            $statusEnum = ShipmentStatus::tryFrom($status);
            $issues = [];
            if ($shipment === null) {
                $issues[] = 'unknown or unreleased tracking number';
            }
            if ($statusEnum === null || ! in_array($statusEnum, ShipmentStatus::eventStatuses(), true)) {
                $issues[] = 'invalid status';
            }
            try {
                $occurred = CarbonImmutable::parse($time);
                if ($occurred->isAfter(now()->addHour())) {
                    $issues[] = 'time is in the future';
                }
            } catch (Throwable) {
                $issues[] = 'invalid time';
                $occurred = null;
            }
            if (mb_strlen($place) > 250) {
                $issues[] = 'place too long';
            }

            if ($issues !== []) {
                $errors[] = "Line $line: ".implode(', ', $issues);

                continue;
            }

            $rows[] = ['number' => $shipment->tracking_number, 'status' => $statusEnum->value, 'label' => $statusEnum->label(), 'place' => $place, 'time' => $occurred->toIso8601String()];
        }
        fclose($handle);

        return [$rows, $errors];
    }

    public function import(ShipmentEventService $events): void
    {
        $saved = 0;
        $failed = [];
        foreach ($this->rows as $index => $row) {
            $shipment = Shipment::query()->where('tracking_number', $row['number'])->first();
            try {
                $events->add($shipment, [
                    'status' => $row['status'], 'label' => $row['label'], 'place' => $row['place'], 'occurred_at' => $row['time'], 'is_public' => true,
                ], auth()->user(), 'import');
                $saved++;
            } catch (DomainRuleException $e) {
                $failed[] = 'Row '.($index + 1).' ('.$row['number'].'): '.$e->getMessage();
            }
        }

        $this->rows = [];
        $this->importErrors = $failed;
        $this->form->fill();
        Notification::make()->success()->title("$saved events imported.".($failed ? ' Some rows failed, see below.' : ''))->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')->label('Preview')->action('preview'),
            Action::make('import')->label('Import valid rows')->color('success')->requiresConfirmation()
                ->visible(fn () => $this->rows !== [])->action('import'),
        ];
    }
}
