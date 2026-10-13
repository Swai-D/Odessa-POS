<?php

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Services\TenantOwnerService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Opens a trial shop for someone who signed up on their own. The shop address is made from the shop name;
 * the owner can use every feature of the trial plan until the trial ends, and nothing is ever deleted after that.
 */
class RegisterShopAction
{
    /** Names that would clash with the app's own addresses. */
    private const RESERVED = ['www', 'app', 'api', 'admin', 'platform', 'login', 'register', 'mail', 'help', 'support', 'static', 'assets', 't', 'up', 'health'];

    public function __construct(
        private readonly ProvisionTenantAction $provision,
        private readonly TenantOwnerService $owners,
    ) {}

    /** @param  array{shop_name: string, owner_name: string, email: string, password: string}  $data */
    public function handle(array $data, string $locale): User
    {
        $tenant = $this->provision->handle([
            'name' => $data['shop_name'],
            'slug' => $this->slugFor($data['shop_name']),
            'plan' => (string) config('plans.trial_plan'),
            'status' => 'trial',
            'owner_name' => $data['owner_name'],
            'owner_email' => strtolower($data['email']),
            'owner_password' => $data['password'],
        ]);

        $settings = (array) $tenant->settings;
        $settings['locale'] = $locale;
        $settings['signup'] = 'self';
        $tenant->update(['settings' => $settings]);

        $owner = $this->owners->find($tenant);
        $owner?->forceFill(['locale' => $locale])->save();

        return $owner ?? throw new \RuntimeException('The shop owner was not created.');
    }

    private function slugFor(string $name): string
    {
        $base = Str::limit(Str::slug($name), 40, '');
        $base = trim($base, '-');

        if ($base === '' || in_array($base, self::RESERVED, true)) {
            $base = 'shop';
        }

        $slug = $base;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
