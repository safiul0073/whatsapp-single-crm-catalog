<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function insertSession(string $id, ?int $userId, ?int $adminId): void
{
    $data = ['_token' => 'x'];

    if ($adminId !== null) {
        $data[Auth::guard('admin')->getName()] = $adminId;
    }

    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => base64_encode(serialize($data)),
        'last_activity' => now()->timestamp,
    ]);
}

it('logs an admin out of every browser and rotates the remember token', function (): void {
    $admin = createAdminUser();
    $other = createAdminUser();
    // audit_logs.user_id references users, so a user sharing the admin's id must exist.
    User::factory()->create(['id' => $admin->id]);
    $admin->setRememberToken('old-token');
    $admin->save();

    insertSession('admin-a-1', null, $admin->id);
    insertSession('admin-a-2', null, $admin->id);
    insertSession('admin-b-1', null, $other->id);
    insertSession('user-same-id', $admin->id, null);

    $this->actingAs($admin, 'admin')->post(route('admin.logout'))->assertRedirect(route('admin.login'));

    expect(DB::table('sessions')->whereIn('id', ['admin-a-1', 'admin-a-2'])->count())->toBe(0)
        ->and(DB::table('sessions')->whereIn('id', ['admin-b-1', 'user-same-id'])->count())->toBe(2)
        ->and($admin->fresh()->getRememberToken())->not->toBe('old-token');
});

it('logs a user out of every browser', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    insertSession('user-1', $user->id, null);
    insertSession('user-2', $user->id, null);
    insertSession('other-1', $other->id, null);

    $this->actingAs($user)->post(route('logout'));

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', 'other-1')->count())->toBe(1);
});
