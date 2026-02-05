<?php

namespace App\Filament\Resources\Customer\PartyResource\Pages;

use App\Filament\Resources\Customer\PartyResource;
use App\Models\AuditLog;
use App\Models\Customer\Party;
use App\Models\Procurement\ProducerSupplierConfig;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @property Form $form
 */
class EditSupplierConfig extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = PartyResource::class;

    protected static string $view = 'filament.resources.customer.party-resource.pages.edit-supplier-config';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    /**
     * @var Party
     */
    public $record;

    public function mount(string $record): void
    {
        $this->record = Party::findOrFail($record);

        // Check if party is supplier or producer
        if (! $this->record->isSupplier() && ! $this->record->isProducer()) {
            Notification::make()
                ->title('Access denied')
                ->body('Only suppliers or producers can have a configuration.')
                ->danger()
                ->send();

            redirect()->route('filament.admin.resources.customer.parties.view', ['record' => $this->record->id]);

            return;
        }

        // Get existing config or prepare for creation
        $config = $this->record->supplierConfig;

        $this->data = [
            'default_bottling_deadline_days' => $config !== null ? $config->default_bottling_deadline_days : null,
            'allowed_formats' => $config !== null && $config->allowed_formats !== null ? $config->allowed_formats : [],
            'serialization_constraints' => $config !== null && $config->serialization_constraints !== null ? $config->serialization_constraints : [],
            'notes' => $config !== null ? $config->notes : null,
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return "Edit Supplier Config: {$this->record->legal_name}";
    }

    public function getSubheading(): string|Htmlable|null
    {
        $hasConfig = $this->record->hasSupplierConfig();

        return $hasConfig
            ? 'Modify the supplier/producer configuration'
            : 'Create a new supplier/producer configuration';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back to Party')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => route('filament.admin.resources.customer.parties.view', ['record' => $this->record->id])),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Bottling Defaults')
                    ->description('Default values used when creating Bottling Instructions')
                    ->schema([
                        Forms\Components\TextInput::make('default_bottling_deadline_days')
                            ->label('Default Bottling Deadline (days)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365)
                            ->helperText('Number of days from intent creation to bottling deadline. Leave empty if no default.')
                            ->suffix('days'),

                        Forms\Components\TagsInput::make('allowed_formats')
                            ->label('Allowed Formats')
                            ->helperText('Enter allowed bottle formats (e.g., 750ml, 1500ml, 3000ml). Leave empty for no restrictions.')
                            ->placeholder('Add format...')
                            ->suggestions(['375ml', '750ml', '1500ml', '3000ml', '6000ml']),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Serialization Constraints')
                    ->description('Constraints for serialization location and routing')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        Forms\Components\KeyValue::make('serialization_constraints')
                            ->label('Constraints')
                            ->keyLabel('Constraint Type')
                            ->valueLabel('Value')
                            ->helperText('Configure serialization constraints. Common keys: authorized_locations, required_location, notes')
                            ->addActionLabel('Add Constraint')
                            ->reorderable(),
                    ]),

                Forms\Components\Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('General Notes')
                            ->rows(4)
                            ->helperText('Any general notes about working with this supplier/producer (e.g., preferred communication methods, lead times, quality requirements)'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->data;

        // Get or create config
        $config = $this->record->supplierConfig;
        $isNew = $config === null;

        if ($isNew) {
            $config = ProducerSupplierConfig::create([
                'party_id' => $this->record->id,
                'default_bottling_deadline_days' => $data['default_bottling_deadline_days'] ?? null,
                'allowed_formats' => ! empty($data['allowed_formats']) ? $data['allowed_formats'] : null,
                'serialization_constraints' => ! empty($data['serialization_constraints']) ? $data['serialization_constraints'] : null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Log creation
            AuditLog::create([
                'auditable_type' => ProducerSupplierConfig::class,
                'auditable_id' => $config->id,
                'event' => AuditLog::EVENT_CREATED,
                'user_id' => auth()->id(),
                'old_values' => [],
                'new_values' => $config->toArray(),
            ]);
        } else {
            $oldValues = $config->toArray();

            $config->update([
                'default_bottling_deadline_days' => $data['default_bottling_deadline_days'] ?? null,
                'allowed_formats' => ! empty($data['allowed_formats']) ? $data['allowed_formats'] : null,
                'serialization_constraints' => ! empty($data['serialization_constraints']) ? $data['serialization_constraints'] : null,
                'notes' => $data['notes'] ?? null,
            ]);

            $freshConfig = $config->fresh();

            // Log update
            AuditLog::create([
                'auditable_type' => ProducerSupplierConfig::class,
                'auditable_id' => $config->id,
                'event' => AuditLog::EVENT_UPDATED,
                'user_id' => auth()->id(),
                'old_values' => $oldValues,
                'new_values' => $freshConfig !== null ? $freshConfig->toArray() : [],
            ]);
        }

        Notification::make()
            ->title($isNew ? 'Configuration created' : 'Configuration updated')
            ->body($isNew
                ? 'The supplier/producer configuration has been created successfully.'
                : 'The supplier/producer configuration has been updated successfully.')
            ->success()
            ->send();

        // Redirect back to party view
        redirect()->route('filament.admin.resources.customer.parties.view', ['record' => $this->record->id, 'activeTab' => 'supplier-config']);
    }
}
