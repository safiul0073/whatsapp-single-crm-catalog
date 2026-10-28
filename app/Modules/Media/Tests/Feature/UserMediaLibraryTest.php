<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureSubscriptionUsable;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Http\Middleware\PanelAccess;
use App\Models\User;
use App\Modules\Media\Models\Media;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutMiddleware([EnsureOnboardingComplete::class, EnsureSubscriptionUsable::class, EnsureTwoFactorAuthenticated::class, PanelAccess::class]);
    Storage::fake('public');
    $this->user = User::factory()->create();
});

function libraryMedia(User $user, string $name, string $type = 'image', string $extension = 'jpg'): Media
{
    return Media::query()->create([
        'name' => $name, 'file_name' => $name.'.'.$extension, 'original_name' => $name.'.'.$extension,
        'mime_type' => $type === 'video' ? 'video/mp4' : 'image/jpeg', 'extension' => $extension, 'type' => $type,
        'size' => 1024, 'disk' => 'public', 'path' => 'media/'.$name.'.'.$extension, 'uploaded_by' => $user->id,
    ]);
}

it('uploads videos and documents, not only images', function (string $file, string $mime, string $type): void {
    $this->actingAs($this->user)
        ->postJson(route('user.media.upload'), ['file' => UploadedFile::fake()->create($file, 20 * 1024, $mime)])
        ->assertCreated()
        ->assertJsonPath('data.type', $type);
})->with([
    'mp4 video over the old 16 MB limit (20 MB)' => ['clip.mp4', 'video/mp4', 'video'],
    'mov video' => ['clip.mov', 'video/quicktime', 'video'],
    'pdf' => ['catalog.pdf', 'application/pdf', 'document'],
]);

it('rejects unsupported or oversized files with a clear message', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('user.media.upload'), ['file' => UploadedFile::fake()->create('tool.exe', 10, 'application/x-msdownload')])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->actingAs($this->user)
        ->postJson(route('user.media.upload'), ['file' => UploadedFile::fake()->create('huge.jpg', 11 * 1024, 'image/jpeg')])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
});

it('filters the library by search and type for the current user only', function (): void {
    libraryMedia($this->user, 'north-face-hoodie');
    libraryMedia($this->user, '67abcd85-d2e3');
    libraryMedia($this->user, 'promo-nordic', 'video', 'mp4');
    libraryMedia(User::factory()->create(), 'not-mine');

    $names = fn (string $query) => collect($this->actingAs($this->user)->getJson(route('user.media.browse').$query)->assertOk()->json('data.items'))->pluck('name')->sort()->values()->all();

    expect($names('?search=no'))->toBe(['north-face-hoodie', 'promo-nordic'])
        ->and($names('?search=no&type=video'))->toBe(['promo-nordic'])
        ->and($names('?search=%25'))->toBe([]);
    $this->actingAs($this->user)->getJson(route('user.media.browse').'?type=bogus')->assertUnprocessable();
});
