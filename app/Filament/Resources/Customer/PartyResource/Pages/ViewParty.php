<?php

namespace App\Filament\Resources\Customer\PartyResource\Pages;

use App\Enums\Customer\PartyRoleType;
use App\Enums\Customer\PartyStatus;
use App\Enums\Customer\PartyType;
use App\Filament\Resources\Customer\PartyResource;
use App\Models\AuditLog;
use App\Models\Customer\Party;
use App\Models\Customer\PartyRole;
use App\Models\Procurement\ProducerSupplierConfig;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\QueryException;

class ViewParty extends ViewRecord
{
    protected static string $resource = PartyResource::class;

    /**
     * Filter for audit log event type.
     */
    public ?string $auditEventFilter = null;

    /**
     * Filter for audit log date from.
     */
    public ?string $auditDateFrom = null;

    /**
     * Filter for audit log date until.
     */
    public ?string $auditDateUntil = null;

    public function getTitle(): string|Htmlable
    {
        /** @var Party $record */
        $record = $this->record;

        return "Party: {$record->legal_name}";
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var Party $record */
        $record = $this->record;

        return $record->party_type->label().' - '.$record->status->label();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            $this->getActivateAction(),
            $this->getDeactivateAction(),
        ];
    }

    /**
     * Activate party action.
     */
    protected function getActivateAction(): Actions\Action
    {
        /** @var Party $record */
        $record = $this->record;

        return Actions\Action::make('activate')
            ->label('Activate')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Activate Party')
            ->modalDescription('Are you sure you want to activate this party? This will allow the party to be used in operations.')
            ->modalSubmitActionLabel('Activate')
            ->action(function () use ($record): void {
                $record->update(['status' => PartyStatus::Active]);

                Notification::make()
                    ->title('Party activated')
                    ->body('The party has been activated successfully.')
                    ->success()
                    ->send();

                $this->refreshFormData(['status']);
            })
            ->visible(fn (): bool => $record->status === PartyStatus::Inactive);
    }

    /**
     * Deactivate party action.
     */
    protected function getDeactivateAction(): Actions\Action
    {
        /** @var Party $record */
        $record = $this->record;

        return Actions\Action::make('deactivate')
            ->label('Deactivate')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Deactivate Party')
            ->modalDescription('Are you sure you want to deactivate this party? This may restrict the party from being used in operations.')
            ->modalSubmitActionLabel('Deactivate')
            ->action(function () use ($record): void {
                $record->update(['status' => PartyStatus::Inactive]);

                Notification::make()
                    ->title('Party deactivated')
                    ->body('The party has been deactivated.')
                    ->success()
                    ->send();

                $this->refreshFormData(['status']);
            })
            ->visible(fn (): bool => $record->status === PartyStatus::Active);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Tabs::make('Party Details')
                    ->tabs([
                        $this->getOverviewTab(),
                        $this->getRolesTab(),
                        $this->getSupplierConfigTab(),
                        $this->getLegalTab(),
                        $this->getAuditTab(),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Tab Overview: identity summary, status, created/updated dates.
     */
    protected function getOverviewTab(): Tab
    {
        return Tab::make('Overview')
            ->icon('heroicon-o-identification')
            ->schema([
                Section::make('Identity Summary')
                    ->description('Basic party information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Group::make([
                                    TextEntry::make('id')
                                        ->label('Party ID')
                                        ->copyable()
                                        ->copyMessage('Party ID copied')
                                        ->weight(FontWeight::Bold),
                                    TextEntry::make('legal_name')
                                        ->label('Legal Name')
                                        ->weight(FontWeight::Bold)
                                        ->size(TextEntry\TextEntrySize::Large),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('party_type')
                                        ->label('Party Type')
                                        ->badge()
                                        ->formatStateUsing(fn (PartyType $state): string => $state->label())
                                        ->color(fn (PartyType $state): string => $state->color())
                                        ->icon(fn (PartyType $state): string => $state->icon()),
                                    TextEntry::make('status')
                                        ->label('Status')
                                        ->badge()
                                        ->formatStateUsing(fn (PartyStatus $state): string => $state->label())
                                        ->color(fn (PartyStatus $state): string => $state->color())
                                        ->icon(fn (PartyStatus $state): string => $state->icon()),
                                ])->columnSpan(1),
                                Group::make([
                                    TextEntry::make('created_at')
                                        ->label('Created')
                                        ->dateTime(),
                                    TextEntry::make('updated_at')
                                        ->label('Last Updated')
                                        ->dateTime(),
                                ])->columnSpan(1),
                            ]),
                    ]),
                Section::make('Assigned Roles')
                    ->description('Roles currently assigned to this party')
                    ->schema([
                        TextEntry::make('roles.role')
                            ->label('')
                            ->badge()
                            ->formatStateUsing(fn (PartyRoleType $state): string => $state->label())
                            ->color(fn (PartyRoleType $state): string => $state->color())
                            ->icon(fn (PartyRoleType $state): string => $state->icon())
                            ->placeholder('No roles assigned')
                            ->separator(', '),
                    ]),
            ]);
    }

    /**
     * Tab Roles: lista ruoli attivi con possibilita di aggiunta/rimozione.
     */
    protected function getRolesTab(): Tab
    {
        /** @var Party $record */
        $record = $this->record;
        $rolesCount = $record->roles()->count();

        return Tab::make('Roles')
            ->icon('heroicon-o-user-group')
            ->badge($rolesCount > 0 ? (string) $rolesCount : null)
            ->badgeColor('info')
            ->schema([
                Section::make('Party Roles')
                    ->description('Manage the roles assigned to this party. A party can have multiple roles simultaneously.')
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('add_role')
                            ->label('Add Role')
                            ->icon('heroicon-o-plus-circle')
                            ->color('primary')
                            ->form([
                                Select::make('role')
                                    ->label('Role')
                                    ->options(function () use ($record): array {
                                        $existingRoles = $record->roles()->pluck('role')->toArray();

                                        return collect(PartyRoleType::cases())
                                            ->filter(fn (PartyRoleType $role): bool => ! in_array($role->value, $existingRoles, true))
                                            ->mapWithKeys(fn (PartyRoleType $role): array => [
                                                $role->value => $role->label(),
                                            ])
                                            ->toArray();
                                    })
                                    ->required()
                                    ->native(false)
                                    ->helperText('Select a role to assign to this party'),
                            ])
                            ->modalHeading('Add Role to Party')
                            ->modalDescription('Select a role to assign to this party.')
                            ->modalSubmitActionLabel('Add Role')
                            ->action(function (array $data) use ($record): void {
                                try {
                                    $role = PartyRoleType::from($data['role']);
                                    $record->addRole($role);

                                    Notification::make()
                                        ->title('Role added')
                                        ->body("The {$role->label()} role has been assigned to this party.")
                                        ->success()
                                        ->send();

                                    $this->refreshFormData(['roles']);
                                } catch (QueryException $e) {
                                    Notification::make()
                                        ->title('Cannot add role')
                                        ->body('This role is already assigned to the party.')
                                        ->danger()
                                        ->send();
                                }
                            }),
                    ])
                    ->schema([
                        RepeatableEntry::make('roles')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('role')
                                            ->label('Role')
                                            ->badge()
                                            ->formatStateUsing(fn (PartyRoleType $state): string => $state->label())
                                            ->color(fn (PartyRoleType $state): string => $state->color())
                                            ->icon(fn (PartyRoleType $state): string => $state->icon()),
                                        TextEntry::make('created_at')
                                            ->label('Assigned On')
                                            ->dateTime(),
                                        TextEntry::make('creator.name')
                                            ->label('Assigned By')
                                            ->default('System'),
                                        \Filament\Infolists\Components\Actions::make([
                                            \Filament\Infolists\Components\Actions\Action::make('remove_role')
                                                ->label('Remove')
                                                ->icon('heroicon-o-trash')
                                                ->color('danger')
                                                ->size('sm')
                                                ->requiresConfirmation()
                                                ->modalHeading('Remove Role')
                                                ->modalDescription(fn (PartyRole $partyRole): string => "Are you sure you want to remove the {$partyRole->role->label()} role from this party?")
                                                ->modalSubmitActionLabel('Remove')
                                                ->action(function (PartyRole $partyRole): void {
                                                    /** @var Party $record */
                                                    $record = $this->record;
                                                    $roleLabel = $partyRole->role->label();

                                                    $record->removeRole($partyRole->role);

                                                    Notification::make()
                                                        ->title('Role removed')
                                                        ->body("The {$roleLabel} role has been removed from this party.")
                                                        ->success()
                                                        ->send();

                                                    $this->refreshFormData(['roles']);
                                                }),
                                        ]),
                                    ]),
                            ])
                            ->columns(1)
                            ->placeholder('No roles assigned to this party. Use the "Add Role" button to assign roles.'),
                    ]),
            ]);
    }

    /**
     * Check if party is a supplier or producer.
     */
    protected function isSupplierOrProducer(): bool
    {
        /** @var Party $record */
        $record = $this->record;

        return $record->isSupplier() || $record->isProducer();
    }

    /**
     * Tab Supplier Config: shows ProducerSupplierConfig for suppliers/producers.
     * Only visible for parties with Supplier or Producer role.
     */
    protected function getSupplierConfigTab(): Tab
    {
        /** @var Party $record */
        $record = $this->record;
        $hasConfig = $record->hasSupplierConfig();

        return Tab::make('Supplier Config')
            ->icon('heroicon-o-cog-6-tooth')
            ->badge(fn (): ?string => $hasConfig ? null : '!')
            ->badgeColor('warning')
            ->visible(fn (): bool => $this->isSupplierOrProducer())
            ->schema([
                // Section when config exists
                Section::make('Supplier/Producer Configuration')
                    ->description('Default configurations for this supplier/producer. These settings are used when creating Purchase Orders and Bottling Instructions.')
                    ->visible(fn (): bool => $hasConfig)
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('edit_config')
                            ->label('Edit Config')
                            ->icon('heroicon-o-pencil')
                            ->color('primary')
                            ->url(fn (): string => route('filament.admin.resources.customer.parties.edit-supplier-config', ['record' => $record->id]))
                            ->visible(fn (): bool => $hasConfig),
                    ])
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Group::make([
                                    TextEntry::make('supplierConfig.default_bottling_deadline_days')
                                        ->label('Default Bottling Deadline')
                                        ->formatStateUsing(function (?int $state): string {
                                            if ($state === null) {
                                                return 'Not specified';
                                            }

                                            return "{$state} days";
                                        })
                                        ->icon('heroicon-o-calendar-days')
                                        ->placeholder('Not specified')
                                        ->helperText('Default number of days for bottling deadline when creating Bottling Instructions'),

                                    TextEntry::make('supplierConfig.allowed_formats')
                                        ->label('Allowed Formats')
                                        ->formatStateUsing(function (?array $state): string {
                                            if ($state === null || count($state) === 0) {
                                                return 'No restrictions (all formats allowed)';
                                            }

                                            return implode(', ', $state);
                                        })
                                        ->icon('heroicon-o-beaker')
                                        ->placeholder('No restrictions')
                                        ->helperText('Allowed bottle formats for this supplier'),
                                ])->columnSpan(1),

                                Group::make([
                                    TextEntry::make('supplierConfig.serialization_constraints')
                                        ->label('Serialization Constraints')
                                        ->formatStateUsing(function (?array $state): string {
                                            if ($state === null || count($state) === 0) {
                                                return 'No constraints configured';
                                            }

                                            $parts = [];

                                            // Check for authorized locations
                                            if (isset($state['authorized_locations']) && is_array($state['authorized_locations'])) {
                                                $locations = $state['authorized_locations'];
                                                $parts[] = 'Authorized locations: '.implode(', ', $locations);
                                            }

                                            // Check for required location
                                            if (isset($state['required_location'])) {
                                                $parts[] = 'Required location: '.$state['required_location'];
                                            }

                                            // Check for any other constraints
                                            if (isset($state['notes'])) {
                                                $parts[] = 'Notes: '.$state['notes'];
                                            }

                                            return count($parts) > 0 ? implode("\n", $parts) : 'No constraints configured';
                                        })
                                        ->icon('heroicon-o-qr-code')
                                        ->placeholder('No constraints')
                                        ->helperText('Serialization location constraints for this supplier'),

                                    TextEntry::make('supplierConfig.notes')
                                        ->label('Notes')
                                        ->icon('heroicon-o-document-text')
                                        ->placeholder('No notes')
                                        ->helperText('General notes about this supplier configuration'),
                                ])->columnSpan(1),
                            ]),

                        // Config metadata
                        Section::make('Configuration Metadata')
                            ->collapsed()
                            ->collapsible()
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextEntry::make('supplierConfig.id')
                                            ->label('Config ID')
                                            ->copyable()
                                            ->copyMessage('Config ID copied'),
                                        TextEntry::make('supplierConfig.created_at')
                                            ->label('Created')
                                            ->dateTime(),
                                        TextEntry::make('supplierConfig.updated_at')
                                            ->label('Last Updated')
                                            ->dateTime(),
                                    ]),
                            ]),
                    ]),

                // Section when config does NOT exist
                Section::make('No Configuration Found')
                    ->description('This supplier/producer does not have a configuration yet.')
                    ->visible(fn (): bool => ! $hasConfig)
                    ->schema([
                        TextEntry::make('no_config_message')
                            ->label('')
                            ->getStateUsing(fn (): string => 'A Supplier/Producer Configuration allows you to set default values that will be used when creating Purchase Orders and Bottling Instructions for this party.')
                            ->icon('heroicon-o-information-circle')
                            ->color('gray'),

                        TextEntry::make('config_benefits')
                            ->label('Benefits of creating a configuration')
                            ->getStateUsing(fn (): string => '• Set default bottling deadline days for Bottling Instructions
• Define allowed bottle formats
• Configure serialization location constraints
• Add general notes about working with this supplier')
                            ->icon('heroicon-o-check-circle')
                            ->color('success'),

                        \Filament\Infolists\Components\Actions::make([
                            \Filament\Infolists\Components\Actions\Action::make('create_config')
                                ->label('Create Config')
                                ->icon('heroicon-o-plus-circle')
                                ->color('primary')
                                ->form([
                                    Forms\Components\TextInput::make('default_bottling_deadline_days')
                                        ->label('Default Bottling Deadline (days)')
                                        ->numeric()
                                        ->minValue(1)
                                        ->helperText('Number of days from intent creation to bottling deadline'),

                                    Forms\Components\TagsInput::make('allowed_formats')
                                        ->label('Allowed Formats')
                                        ->helperText('Enter allowed bottle formats (e.g., 750ml, 1500ml)')
                                        ->placeholder('Add formats...'),

                                    Forms\Components\KeyValue::make('serialization_constraints')
                                        ->label('Serialization Constraints')
                                        ->keyLabel('Constraint Type')
                                        ->valueLabel('Value')
                                        ->helperText('e.g., authorized_locations: france,italy'),

                                    Forms\Components\Textarea::make('notes')
                                        ->label('Notes')
                                        ->rows(3)
                                        ->helperText('General notes about working with this supplier'),
                                ])
                                ->modalHeading('Create Supplier Configuration')
                                ->modalDescription('Create a new configuration for this supplier/producer.')
                                ->modalSubmitActionLabel('Create')
                                ->action(function (array $data) use ($record): void {
                                    ProducerSupplierConfig::create([
                                        'party_id' => $record->id,
                                        'default_bottling_deadline_days' => $data['default_bottling_deadline_days'] ?? null,
                                        'allowed_formats' => ! empty($data['allowed_formats']) ? $data['allowed_formats'] : null,
                                        'serialization_constraints' => ! empty($data['serialization_constraints']) ? $data['serialization_constraints'] : null,
                                        'notes' => $data['notes'] ?? null,
                                    ]);

                                    Notification::make()
                                        ->title('Configuration created')
                                        ->body('The supplier/producer configuration has been created successfully.')
                                        ->success()
                                        ->send();

                                    // Refresh the page to show the new config
                                    redirect()->route('filament.admin.resources.customer.parties.view', ['record' => $record->id]);
                                }),
                        ])->fullWidth(),
                    ]),
            ]);
    }

    /**
     * Tab Legal: tax_id, vat_number, jurisdiction, compliance notes.
     */
    protected function getLegalTab(): Tab
    {
        return Tab::make('Legal')
            ->icon('heroicon-o-scale')
            ->schema([
                Section::make('Tax & Legal Information')
                    ->description('Legal identifiers and jurisdiction details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('tax_id')
                                    ->label('Tax ID')
                                    ->copyable()
                                    ->copyMessage('Tax ID copied')
                                    ->placeholder('Not provided')
                                    ->icon('heroicon-o-identification'),
                                TextEntry::make('vat_number')
                                    ->label('VAT Number')
                                    ->copyable()
                                    ->copyMessage('VAT Number copied')
                                    ->placeholder('Not provided')
                                    ->icon('heroicon-o-receipt-percent'),
                                TextEntry::make('jurisdiction')
                                    ->label('Jurisdiction')
                                    ->placeholder('Not specified')
                                    ->icon('heroicon-o-globe-alt'),
                            ]),
                    ]),
                Section::make('Compliance Notes')
                    ->description('Additional compliance-related information')
                    ->collapsed()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('compliance_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'No compliance notes recorded for this party.')
                            ->icon('heroicon-o-information-circle')
                            ->color('gray'),
                    ]),
            ]);
    }

    /**
     * Tab Audit: timeline read-only di tutte le modifiche.
     */
    protected function getAuditTab(): Tab
    {
        /** @var Party $record */
        $record = $this->record;
        $auditCount = $record->auditLogs()->count();

        return Tab::make('Audit')
            ->icon('heroicon-o-document-text')
            ->badge($auditCount > 0 ? (string) $auditCount : null)
            ->badgeColor('gray')
            ->schema([
                Section::make('Audit Trail')
                    ->description(fn (): string => $this->getAuditFilterDescription())
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('filter_audit')
                            ->label('Filter')
                            ->icon('heroicon-o-funnel')
                            ->form([
                                Select::make('event_type')
                                    ->label('Event Type')
                                    ->placeholder('All events')
                                    ->options([
                                        AuditLog::EVENT_CREATED => 'Created',
                                        AuditLog::EVENT_UPDATED => 'Updated',
                                        AuditLog::EVENT_DELETED => 'Deleted',
                                        AuditLog::EVENT_STATUS_CHANGE => 'Status Changed',
                                    ])
                                    ->default($this->auditEventFilter),
                                DatePicker::make('date_from')
                                    ->label('From Date')
                                    ->default($this->auditDateFrom),
                                DatePicker::make('date_until')
                                    ->label('Until Date')
                                    ->default($this->auditDateUntil),
                            ])
                            ->action(function (array $data): void {
                                $this->auditEventFilter = $data['event_type'] ?? null;
                                $this->auditDateFrom = $data['date_from'] ?? null;
                                $this->auditDateUntil = $data['date_until'] ?? null;
                            }),
                        \Filament\Infolists\Components\Actions\Action::make('clear_filters')
                            ->label('Clear Filters')
                            ->icon('heroicon-o-x-mark')
                            ->color('gray')
                            ->visible(fn (): bool => $this->auditEventFilter !== null || $this->auditDateFrom !== null || $this->auditDateUntil !== null)
                            ->action(function (): void {
                                $this->auditEventFilter = null;
                                $this->auditDateFrom = null;
                                $this->auditDateUntil = null;
                            }),
                    ])
                    ->schema([
                        TextEntry::make('audit_logs_list')
                            ->label('')
                            ->getStateUsing(function (Party $record): string {
                                $query = $record->auditLogs()->orderBy('created_at', 'desc');

                                if ($this->auditEventFilter) {
                                    $query->where('event', $this->auditEventFilter);
                                }

                                if ($this->auditDateFrom) {
                                    $query->whereDate('created_at', '>=', $this->auditDateFrom);
                                }

                                if ($this->auditDateUntil) {
                                    $query->whereDate('created_at', '<=', $this->auditDateUntil);
                                }

                                $logs = $query->get();

                                if ($logs->isEmpty()) {
                                    return '<div class="text-gray-500 text-sm py-4">No audit logs found matching the current filters.</div>';
                                }

                                $html = '<div class="space-y-3">';
                                foreach ($logs as $log) {
                                    /** @var AuditLog $log */
                                    $eventColor = $log->getEventColor();
                                    $eventLabel = $log->getEventLabel();
                                    $user = $log->user;
                                    $userName = $user !== null ? $user->name : 'System';
                                    $timestamp = $log->created_at->format('M d, Y H:i:s');
                                    $changes = self::formatAuditChanges($log);

                                    $colorClass = match ($eventColor) {
                                        'success' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                        'danger' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                                        'warning' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                        'info' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                        default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                    };

                                    $html .= <<<HTML
                                    <div class="flex items-start gap-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                        <div class="flex-shrink-0">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {$colorClass}">
                                                {$eventLabel}
                                            </span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400 mb-1">
                                                <span class="flex items-center gap-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                                    {$userName}
                                                </span>
                                                <span class="flex items-center gap-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                    {$timestamp}
                                                </span>
                                            </div>
                                            <div class="text-sm">{$changes}</div>
                                        </div>
                                    </div>
                                    HTML;
                                }
                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
                Section::make('Audit Information')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('audit_info')
                            ->label('')
                            ->getStateUsing(fn (): string => 'Audit logs are immutable and cannot be modified or deleted. They provide a complete history of all changes to this party for compliance and traceability purposes.')
                            ->icon('heroicon-o-information-circle')
                            ->color('gray'),
                    ]),
            ]);
    }

    /**
     * Get the filter description for the audit section.
     */
    protected function getAuditFilterDescription(): string
    {
        $parts = ['Immutable audit trail of all changes to this party'];

        $filters = [];
        if ($this->auditEventFilter) {
            $eventLabel = match ($this->auditEventFilter) {
                AuditLog::EVENT_CREATED => 'Created',
                AuditLog::EVENT_UPDATED => 'Updated',
                AuditLog::EVENT_DELETED => 'Deleted',
                AuditLog::EVENT_STATUS_CHANGE => 'Status Changed',
                default => $this->auditEventFilter,
            };
            $filters[] = "Event: {$eventLabel}";
        }
        if ($this->auditDateFrom) {
            $filters[] = "From: {$this->auditDateFrom}";
        }
        if ($this->auditDateUntil) {
            $filters[] = "Until: {$this->auditDateUntil}";
        }

        if (! empty($filters)) {
            $parts[] = 'Filters: '.implode(', ', $filters);
        }

        return implode(' | ', $parts);
    }

    /**
     * Format audit log changes for display.
     */
    protected static function formatAuditChanges(AuditLog $log): string
    {
        $oldValues = $log->old_values ?? [];
        $newValues = $log->new_values ?? [];

        if ($log->event === AuditLog::EVENT_CREATED) {
            $fieldCount = count($newValues);

            return "<span class='text-sm text-gray-500'>{$fieldCount} field(s) set</span>";
        }

        if ($log->event === AuditLog::EVENT_DELETED) {
            return "<span class='text-sm text-gray-500'>Record deleted</span>";
        }

        $changes = [];
        $allFields = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

        foreach ($allFields as $field) {
            $oldValue = $oldValues[$field] ?? null;
            $newValue = $newValues[$field] ?? null;

            if ($oldValue !== $newValue) {
                $fieldLabel = ucfirst(str_replace('_', ' ', $field));
                $oldDisplay = self::formatValue($oldValue);
                $newDisplay = self::formatValue($newValue);
                $changes[] = "<strong>{$fieldLabel}</strong>: {$oldDisplay} → {$newDisplay}";
            }
        }

        return count($changes) > 0
            ? '<span class="text-sm">'.implode('<br>', $changes).'</span>'
            : '<span class="text-sm text-gray-500">No field changes</span>';
    }

    /**
     * Format a value for display in audit logs.
     */
    protected static function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '<em class="text-gray-400">empty</em>';
        }

        if (is_array($value)) {
            return '<em class="text-gray-500">['.count($value).' items]</em>';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        $stringValue = (string) $value;
        if (strlen($stringValue) > 50) {
            return htmlspecialchars(substr($stringValue, 0, 47)).'...';
        }

        return htmlspecialchars($stringValue);
    }
}
