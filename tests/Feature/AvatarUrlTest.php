<?php

use App\Models\User;

it('uses external social avatar urls without storage prefixing', function (): void {
    $url = 'https://lh3.googleusercontent.com/a/ACg8ocKziyE9cNEKbJmWl8jvgRZFTRjK--vDWfPyLVBgxBGf_lhkzg=s96-c';
    $user = User::factory()->make([
        'name' => 'Google User',
        'avatar' => $url,
    ]);

    expect(avatar_url($url))->toBe($url);

    $html = view('panels.admin.users.columns.name', [
        'record' => $user,
    ])->render();

    expect($html)
        ->toContain('src="'.$url.'"')
        ->not->toContain('/storage/https://');
});

it('still resolves local avatar paths through storage', function (): void {
    expect(avatar_url('avatars/profile.jpg'))->toContain('/storage/avatars/profile.jpg');
});
