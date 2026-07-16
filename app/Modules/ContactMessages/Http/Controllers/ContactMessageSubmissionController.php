<?php

namespace App\Modules\ContactMessages\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ContactMessages\Http\Requests\StoreContactMessageRequest;
use App\Modules\ContactMessages\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ContactMessageSubmissionController extends Controller
{
    public function store(StoreContactMessageRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        $message = ContactMessage::create($data + [
            'ip_address' => $request->ip(),
            'user_agent' => str($request->userAgent() ?? '')->limit(500, '')->value(),
            'source_url' => str($request->headers->get('referer') ?? url()->previous())->limit(2048, '')->value(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message_id' => $message->id,
            ]);
        }

        return back()->with('contact_success', __("Thanks! Your message is on its way. We'll reply within one business day."));
    }
}
