<?php

namespace App\Modules\Newsletter\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Newsletter\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        Subscriber::updateOrCreate(
            ['email' => $data['email']],
            ['active' => true]
        );

        if (! $request->expectsJson()) {
            return back()->with('newsletter_success', __('Thank you for subscribing!'));
        }

        return response()->json([
            'success' => true,
            'message' => __('Thank you for subscribing!'),
        ]);
    }
}
