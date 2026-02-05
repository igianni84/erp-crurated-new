<?php

namespace Database\Seeders;

use App\Enums\Customer\PartyRoleType;
use App\Enums\Customer\PartyStatus;
use App\Enums\Customer\PartyType;
use App\Models\Customer\Party;
use App\Models\Customer\PartyRole;
use Illuminate\Database\Seeder;

/**
 * PartySeeder - Creates parties (producers, suppliers, partners)
 *
 * Parties represent all counterparties in the system: customers, suppliers, producers, partners.
 * This seeder creates wine producers and suppliers for the procurement module.
 */
class PartySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $parties = [
            // Italian Wine Producers (Piedmont)
            [
                'legal_name' => 'Giacomo Conterno S.r.l.',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'IT01234567890',
                'vat_number' => 'IT01234567890',
                'jurisdiction' => 'IT',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Producer, PartyRoleType::Supplier],
            ],
            [
                'legal_name' => 'Bruno Giacosa S.p.A.',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'IT02345678901',
                'vat_number' => 'IT02345678901',
                'jurisdiction' => 'IT',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Producer, PartyRoleType::Supplier],
            ],
            [
                'legal_name' => 'Gaja S.p.A.',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'IT03456789012',
                'vat_number' => 'IT03456789012',
                'jurisdiction' => 'IT',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Producer, PartyRoleType::Supplier],
            ],
            // Italian Wine Producers (Tuscany)
            [
                'legal_name' => 'Biondi-Santi S.r.l.',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'IT04567890123',
                'vat_number' => 'IT04567890123',
                'jurisdiction' => 'IT',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Producer, PartyRoleType::Supplier],
            ],
            [
                'legal_name' => 'Tenuta San Guido S.r.l.',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'IT05678901234',
                'vat_number' => 'IT05678901234',
                'jurisdiction' => 'IT',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Producer, PartyRoleType::Supplier],
            ],
            [
                'legal_name' => 'Tenuta dell\'Ornellaia S.r.l.',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'IT06789012345',
                'vat_number' => 'IT06789012345',
                'jurisdiction' => 'IT',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Producer, PartyRoleType::Supplier],
            ],
            [
                'legal_name' => 'Marchesi Antinori S.p.A.',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'IT07890123456',
                'vat_number' => 'IT07890123456',
                'jurisdiction' => 'IT',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Producer, PartyRoleType::Supplier],
            ],
            [
                'legal_name' => 'Castello Banfi S.r.l.',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'IT08901234567',
                'vat_number' => 'IT08901234567',
                'jurisdiction' => 'IT',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Producer, PartyRoleType::Supplier],
            ],
            // Italian Wine Producers (Veneto)
            [
                'legal_name' => 'Giuseppe Quintarelli Azienda Agricola',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'IT09012345678',
                'vat_number' => 'IT09012345678',
                'jurisdiction' => 'IT',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Producer, PartyRoleType::Supplier],
            ],
            [
                'legal_name' => 'Bertani Domains S.r.l.',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'IT10123456789',
                'vat_number' => 'IT10123456789',
                'jurisdiction' => 'IT',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Producer, PartyRoleType::Supplier],
            ],
            // French Wine Producers (Bordeaux)
            [
                'legal_name' => 'Château Margaux SAS',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'FR12345678901',
                'vat_number' => 'FR12345678901',
                'jurisdiction' => 'FR',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Producer, PartyRoleType::Supplier],
            ],
            [
                'legal_name' => 'Château Latour SAS',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'FR23456789012',
                'vat_number' => 'FR23456789012',
                'jurisdiction' => 'FR',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Producer, PartyRoleType::Supplier],
            ],
            // French Wine Producers (Burgundy)
            [
                'legal_name' => 'Domaine de la Romanée-Conti SA',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'FR34567890123',
                'vat_number' => 'FR34567890123',
                'jurisdiction' => 'FR',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Producer, PartyRoleType::Supplier],
            ],
            [
                'legal_name' => 'Domaine Comte Georges de Vogüé',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'FR45678901234',
                'vat_number' => 'FR45678901234',
                'jurisdiction' => 'FR',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Producer, PartyRoleType::Supplier],
            ],
            // Wine Merchants & Distributors (Suppliers)
            [
                'legal_name' => 'Berry Bros. & Rudd Ltd.',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'GB123456789',
                'vat_number' => 'GB123456789',
                'jurisdiction' => 'GB',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Supplier],
            ],
            [
                'legal_name' => 'Justerini & Brooks Ltd.',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'GB234567890',
                'vat_number' => 'GB234567890',
                'jurisdiction' => 'GB',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Supplier],
            ],
            [
                'legal_name' => 'Farr Vintners Ltd.',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'GB345678901',
                'vat_number' => 'GB345678901',
                'jurisdiction' => 'GB',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Supplier],
            ],
            [
                'legal_name' => 'BI Wines & Spirits S.r.l.',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'IT11234567890',
                'vat_number' => 'IT11234567890',
                'jurisdiction' => 'IT',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Supplier],
            ],
            // Logistics Partners
            [
                'legal_name' => 'Octavian Vaults Ltd.',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'GB456789012',
                'vat_number' => 'GB456789012',
                'jurisdiction' => 'GB',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Partner],
            ],
            [
                'legal_name' => 'London City Bond Ltd.',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'GB567890123',
                'vat_number' => 'GB567890123',
                'jurisdiction' => 'GB',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Partner],
            ],
            [
                'legal_name' => 'Vinotheque Wine Storage SA',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'CH123456789',
                'vat_number' => 'CHE-123.456.789',
                'jurisdiction' => 'CH',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Partner],
            ],
            // Individual producer (wine consultant)
            [
                'legal_name' => 'Roberto Conterno',
                'party_type' => PartyType::Individual,
                'tax_id' => 'CNTRBR65A01L219X',
                'vat_number' => null,
                'jurisdiction' => 'IT',
                'status' => PartyStatus::Active,
                'roles' => [PartyRoleType::Partner],
            ],
            // Inactive supplier
            [
                'legal_name' => 'Vini Italia Distribution S.r.l.',
                'party_type' => PartyType::LegalEntity,
                'tax_id' => 'IT12345678901',
                'vat_number' => 'IT12345678901',
                'jurisdiction' => 'IT',
                'status' => PartyStatus::Inactive,
                'roles' => [PartyRoleType::Supplier],
            ],
        ];

        foreach ($parties as $partyData) {
            $roles = $partyData['roles'];
            unset($partyData['roles']);

            $party = Party::firstOrCreate(
                ['legal_name' => $partyData['legal_name']],
                $partyData
            );

            // Create roles for the party
            foreach ($roles as $role) {
                PartyRole::firstOrCreate([
                    'party_id' => $party->id,
                    'role' => $role,
                ]);
            }
        }
    }
}
