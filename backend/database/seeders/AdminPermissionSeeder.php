<?php
// backend/database/seeders/AdminPermissionSeeder.php
namespace Database\Seeders;

use App\Support\AdminPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class AdminPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AdminPermissions::keys() as $key) {
            Permission::findOrCreate($key, AdminPermissions::GUARD);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
