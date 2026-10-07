<?php

namespace Database\Seeders;

use App\Domain\Settings\Actions\ProvisionTenantAction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $superAdmin = User::query()->withoutGlobalScope('tenant')->firstOrCreate(
            ['email' => config('pos.seed.super_admin_email')],
            [
                'name' => 'Odessa Super Admin',
                'password' => config('pos.seed.super_admin_password') ?: Str::random(48),
            ],
        );
        if ($password = config('pos.seed.super_admin_password')) {
            $superAdmin->password = $password;
        }
        $superAdmin->forceFill(['tenant_id' => null, 'is_super_admin' => true, 'locale' => 'en'])->save();

        app(ProvisionTenantAction::class)->handle([
            'name' => 'Demo Store',
            'slug' => 'demo',
            'plan' => 'demo',
            'owner_name' => 'Demo Owner',
            'owner_email' => (string) config('pos.seed.demo_owner_email'),
            'owner_password' => config('pos.seed.demo_owner_password'),
        ]);

        if ($password = config('pos.seed.demo_owner_password')) {
            User::query()->withoutGlobalScope('tenant')->where('email', config('pos.seed.demo_owner_email'))
                ->first()?->forceFill(['password' => $password])->save();
        }
    }
}
