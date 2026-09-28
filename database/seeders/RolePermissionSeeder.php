<?php

namespace Database\Seeders;

use App\Domains\Auth\Models\Permission;
use App\Domains\Auth\Models\Role;
use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Roles
        $roles = [
            Role::PLATFORM_OWNER => [
                'name' => 'Platform Owner',
                'description' => 'Global owner with complete control across all companies and platform configuration.',
            ],
            Role::COMPANY_ADMIN => [
                'name' => 'Company Admin',
                'description' => 'Administrator of an individual rented company bingo installation.',
            ],
            Role::GAME_MANAGER => [
                'name' => 'Game Manager',
                'description' => 'Conducts and hosts live bingo games for a specific company.',
            ],
            Role::PLAYER => [
                'name' => 'Player',
                'description' => 'Bingo player participating in company bingo games.',
            ],
        ];

        $roleModels = [];
        foreach ($roles as $slug => $data) {
            $roleModels[$slug] = Role::firstOrCreate(['slug' => $slug], $data);
        }

        // 2. Create Permissions
        $permissions = [
            // Platform
            'manage_companies' => 'Create, configure, suspend, and manage tenant companies',
            'view_global_analytics' => 'View platform-wide metrics and revenue',
            'manage_platform_settings' => 'Modify platform-level configuration',

            // Company Admin
            'manage_cards' => 'Generate and manage company fixed card inventory',
            'manage_templates' => 'Create and customize game templates',
            'manage_games' => 'Create and schedule company games',
            'manage_players' => 'View and manage company players',
            'manage_company_finances' => 'View company transactions, prizes, and ledger',
            'view_audit_logs' => 'Inspect company game replay and audit logs',

            // Game Manager
            'operate_games' => 'Start, pause, and conduct live bingo sessions',
            'call_numbers' => 'Trigger number calling cycles',
            'verify_claims' => 'Inspect and verify player bingo claims',

            // Player
            'join_games' => 'Join open games and get assigned fixed cards',
            'claim_bingo' => 'Submit bingo claims during active games',
            'view_game_history' => 'View past games and card marks',
        ];

        $permissionModels = [];
        foreach ($permissions as $slug => $description) {
            $permissionModels[$slug] = Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => ucwords(str_replace('_', ' ', $slug)),
                    'description' => $description,
                ]
            );
        }

        // 3. Assign Permissions to Roles
        // Platform owner gets all permissions
        $roleModels[Role::PLATFORM_OWNER]->permissions()->sync(
            collect($permissionModels)->pluck('id')->all()
        );

        // Company Admin permissions
        $companyAdminPermissions = [
            'manage_cards', 'manage_templates', 'manage_games',
            'manage_players', 'manage_company_finances', 'view_audit_logs',
            'operate_games', 'call_numbers', 'verify_claims',
        ];
        $roleModels[Role::COMPANY_ADMIN]->permissions()->sync(
            collect($permissionModels)->only($companyAdminPermissions)->pluck('id')->all()
        );

        // Game Manager permissions
        $gameManagerPermissions = ['operate_games', 'call_numbers', 'verify_claims', 'view_audit_logs'];
        $roleModels[Role::GAME_MANAGER]->permissions()->sync(
            collect($permissionModels)->only($gameManagerPermissions)->pluck('id')->all()
        );

        // Player permissions
        $playerPermissions = ['join_games', 'claim_bingo', 'view_game_history'];
        $roleModels[Role::PLAYER]->permissions()->sync(
            collect($permissionModels)->only($playerPermissions)->pluck('id')->all()
        );

        // 4. Create Sample Companies
        $acme = Company::firstOrCreate(
            ['slug' => 'acme-bingo'],
            [
                'name' => 'Acme Bingo Club',
                'status' => 'active',
                'settings' => [
                    'brand_color' => '#4f46e5',
                    'tagline' => 'The Premier Online Bingo Experience',
                    'currency' => 'USD',
                ],
            ]
        );

        $luckyStar = Company::firstOrCreate(
            ['slug' => 'lucky-star'],
            [
                'name' => 'Lucky Star Gaming',
                'status' => 'active',
                'settings' => [
                    'brand_color' => '#059669',
                    'tagline' => 'High Stakes & Fun Everyday',
                    'currency' => 'USD',
                ],
            ]
        );

        // 5. Seed Users
        $defaultPassword = Hash::make('Password123!');

        // Platform Owner (no company_id)
        $owner = User::firstOrCreate(
            ['email' => 'owner@nobingo.test'],
            [
                'name' => 'System Owner',
                'password' => $defaultPassword,
                'company_id' => null,
                'balance' => 0,
                'status' => 'active',
            ]
        );
        $owner->roles()->sync([$roleModels[Role::PLATFORM_OWNER]->id]);

        // Acme Admin
        $acmeAdmin = User::firstOrCreate(
            ['email' => 'admin@acme.test'],
            [
                'name' => 'Acme Administrator',
                'password' => $defaultPassword,
                'company_id' => $acme->id,
                'balance' => 0,
                'status' => 'active',
            ]
        );
        $acmeAdmin->roles()->sync([$roleModels[Role::COMPANY_ADMIN]->id]);

        // Acme Game Manager
        $acmeManager = User::firstOrCreate(
            ['email' => 'manager@acme.test'],
            [
                'name' => 'Acme Caller',
                'password' => $defaultPassword,
                'company_id' => $acme->id,
                'balance' => 0,
                'status' => 'active',
            ]
        );
        $acmeManager->roles()->sync([$roleModels[Role::GAME_MANAGER]->id]);

        // Acme Player 1
        $acmePlayer = User::firstOrCreate(
            ['email' => 'player1@acme.test'],
            [
                'name' => 'Alice Player',
                'password' => $defaultPassword,
                'company_id' => $acme->id,
                'balance' => 5000, // $50.00
                'status' => 'active',
            ]
        );
        $acmePlayer->roles()->sync([$roleModels[Role::PLAYER]->id]);

        // Lucky Star Admin
        $luckyAdmin = User::firstOrCreate(
            ['email' => 'admin@luckystar.test'],
            [
                'name' => 'Lucky Star Admin',
                'password' => $defaultPassword,
                'company_id' => $luckyStar->id,
                'balance' => 0,
                'status' => 'active',
            ]
        );
        $luckyAdmin->roles()->sync([$roleModels[Role::COMPANY_ADMIN]->id]);

        // Lucky Star Player 2
        $luckyPlayer = User::firstOrCreate(
            ['email' => 'player2@luckystar.test'],
            [
                'name' => 'Bob Player',
                'password' => $defaultPassword,
                'company_id' => $luckyStar->id,
                'balance' => 2500, // $25.00
                'status' => 'active',
            ]
        );
        $luckyPlayer->roles()->sync([$roleModels[Role::PLAYER]->id]);
    }
}
