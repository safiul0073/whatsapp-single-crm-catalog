<?php

namespace App\Modules\Chatbots\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chatbots\Models\ChatbotWidgetSession;
use App\Modules\Chatbots\Services\ChatbotWidgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicChatbotWidgetController extends Controller
{
    public function loader(string $token, Request $request, ChatbotWidgetService $widgets): Response
    {
        $widget = $widgets->publicWidget($token, $request);
        $baseUrl = url('/');
        $configUrl = route('widgets.chatbot.config', $widget->public_token);
        $script = view('chatbots::public.loader', compact('baseUrl', 'configUrl', 'token'))->render();

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function config(string $token, Request $request, ChatbotWidgetService $widgets): JsonResponse
    {
        $widget = $widgets->publicWidget($token, $request);

        return $this->cors(response()->json($widgets->publicConfig($widget)), $request);
    }

    public function session(string $token, Request $request, ChatbotWidgetService $widgets): JsonResponse
    {
        $widget = $widgets->publicWidget($token, $request);
        $validated = $request->validate([
            'visitor_uid' => ['nullable', 'string', 'max:100'],
            'session_token' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'page_url' => ['nullable', 'url', 'max:500'],
            'timezone' => ['nullable', 'string', 'max:80'],
        ]);

        $session = $widgets->startSession($widget, $request, $validated);

        return $this->cors(response()->json([
            'session_token' => $session->session_token,
            'visitor_uid' => $session->visitor_uid,
            'conversation_id' => $session->conversation_id,
        ]), $request);
    }

    public function message(string $token, Request $request, ChatbotWidgetService $widgets): JsonResponse
    {
        $widget = $widgets->publicWidget($token, $request);
        $validated = $request->validate([
            'session_token' => ['required', 'string', 'max:100'],
            'message' => ['nullable', 'required_without:attachment', 'string', 'min:1', 'max:1000'],
            'attachment' => ['nullable', 'file', 'max:16384', 'mimetypes:image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,audio/mpeg,audio/mp4,audio/ogg,audio/wav,application/pdf,text/plain,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ]);
        $session = ChatbotWidgetSession::query()
            ->where('widget_id', $widget->id)
            ->where('session_token', $validated['session_token'])
            ->firstOrFail();

        return $this->cors(response()->json(
            $widgets->receiveMessage($widget, $session, (string) ($validated['message'] ?? ''), $request->file('attachment'))
        ), $request);
    }

    public function messages(string $token, string $session, Request $request, ChatbotWidgetService $widgets): JsonResponse
    {
        $widget = $widgets->publicWidget($token, $request);
        $widgetSession = ChatbotWidgetSession::query()
            ->where('widget_id', $widget->id)
            ->where('session_token', $session)
            ->firstOrFail();

        return $this->cors(response()->json([
            'messages' => $widgets->messages($widget, $widgetSession, $request->integer('after_id'))->values(),
        ]), $request);
    }

    public function options(string $token, Request $request, ChatbotWidgetService $widgets): Response
    {
        $widgets->publicWidget($token, $request);

        return $this->cors(response('', 204), $request);
    }

    protected function cors(Response|JsonResponse $response, Request $request): Response|JsonResponse
    {
        $origin = $request->headers->get('origin', '*');

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept');
        $response->headers->set('Vary', 'Origin');

        return $response;
    }
}
