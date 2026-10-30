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

it('uploads .ico favicons as images', function (string $mime): void {
    $this->actingAs($this->user)
        ->postJson(route('user.media.upload'), ['file' => UploadedFile::fake()->create('favicon.ico', 12, $mime)])
        ->assertCreated()
        ->assertJsonPath('data.type', 'image')
        ->assertJsonPath('data.extension', 'ico');
})->with(['image/x-icon', 'image/vnd.microsoft.icon']);

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

it('deletes one of the current user\'s files and removes it from disk', function (): void {
    Storage::disk('public')->put('media/mine.jpg', 'x');
    $mine = libraryMedia($this->user, 'mine');

    $this->actingAs($this->user)->deleteJson(route('user.media.destroy', $mine))->assertOk()->assertJsonPath('data.deleted', 1);

    expect(Media::query()->find($mine->id))->toBeNull();
    Storage::disk('public')->assertMissing('media/mine.jpg');
});

it('does not delete files uploaded by someone else', function (): void {
    $theirs = libraryMedia(User::factory()->create(), 'theirs');

    $this->actingAs($this->user)->deleteJson(route('user.media.destroy', $theirs))->assertNotFound();

    expect(Media::query()->find($theirs->id))->not->toBeNull();
});

it('bulk deletes only the current user\'s files and validates the id list', function (): void {
    $a = libraryMedia($this->user, 'a');
    $b = libraryMedia($this->user, 'b');
    $theirs = libraryMedia(User::factory()->create(), 'theirs');

    $this->actingAs($this->user)
        ->deleteJson(route('user.media.bulk-destroy'), ['ids' => [$a->id, $b->id, $theirs->id]])
        ->assertOk()->assertJsonPath('data.deleted', 2);

    expect(Media::query()->whereKey([$a->id, $b->id])->count())->toBe(0)
        ->and(Media::query()->find($theirs->id))->not->toBeNull();

    $this->actingAs($this->user)->deleteJson(route('user.media.bulk-destroy'), ['ids' => []])->assertUnprocessable();
    $this->actingAs($this->user)->deleteJson(route('user.media.bulk-destroy'), ['ids' => ['x']])->assertUnprocessable();
});
