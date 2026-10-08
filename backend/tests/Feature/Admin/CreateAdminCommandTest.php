<?php

// backend/tests/Feature/Admin/CreateAdminCommandTest.php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_super_admin_with_given_password(): void
    {
        $this->artisan('admin:create', [
            '--email' => 'root@roofly.my',
            '--name' => 'Root',
            '--password' => 'correct-horse-battery',
        ])->assertSuccessful();

        $user = User::where('email', 'root@roofly.my')->firstOrFail();
        $this->assertTrue($user->is_super_admin);
        $this->assertSame('admin', $user->role->value);
        $this->assertTrue(Hash::check('correct-horse-battery', $user->password));
    }

    public function test_generates_password_when_omitted_and_can_log_in(): void
    {
        $this->artisan('admin:create', ['--email' => 'gen@roofly.my', '--name' => 'Gen'])
            ->expectsOutputToContain('Generated password')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'gen@roofly.my', 'is_super_admin' => true]);
    }

    public function test_no_super_flag_creates_plain_admin(): void
    {
        $this->artisan('admin:create', [
            '--email' => 'plain@roofly.my', '--name' => 'Plain', '--password' => 'correct-horse-battery', '--no-super' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'plain@roofly.my', 'is_super_admin' => false]);
    }

    public function test_rejects_duplicate_email_and_short_password(): void
    {
        User::factory()->admin()->create(['email' => 'dup@roofly.my']);

        $this->artisan('admin:create', ['--email' => 'dup@roofly.my', '--name' => 'Dup', '--password' => 'correct-horse-battery'])
            ->assertFailed();
        $this->artisan('admin:create', ['--email' => 'short@roofly.my', '--name' => 'Short', '--password' => 'short'])
            ->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'short@roofly.my']);
    }
}
