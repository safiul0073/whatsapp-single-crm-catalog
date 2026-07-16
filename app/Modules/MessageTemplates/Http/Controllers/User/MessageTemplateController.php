<?php

namespace App\Modules\MessageTemplates\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MessageTemplates\Http\Requests\StoreMessageTemplateRequest;
use App\Modules\MessageTemplates\Http\Requests\SubmitMessageTemplateRequest;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\MessageTemplates\Services\MessageTemplateAiGeneratorService;
use App\Modules\MessageTemplates\Services\MessageTemplateService;
use App\Modules\PlansSubscriptions\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageTemplateController extends Controller
{
    public function index(Request $request, MessageTemplateService $service): View
    {
        $provider = in_array($request->query('provider'), ['whatsapp', 'telegram'], true)
            ? (string) $request->query('provider')
            : 'whatsapp';

        return view('message-templates::user.index', [
            'provider' => $provider,
            'templates' => $service->listForUser($request->user(), $provider),
            'stats' => $service->statsForUser($request->user(), $provider),
            'wabas' => $service->wabasForUser($request->user()),
        ]);
    }

    public function create(Request $request, MessageTemplateService $service): View
    {
        $provider = in_array($request->query('provider'), ['whatsapp', 'telegram'], true)
            ? (string) $request->query('provider')
            : 'whatsapp';

        return view('message-templates::user.create', [
            'provider' => $provider,
            'wabas' => $service->wabasForUser($request->user()),
            'template' => null,
        ]);
    }

    public function store(StoreMessageTemplateRequest $request, MessageTemplateService $service): RedirectResponse
    {
        $service->store($request->user(), $request->validated(), $request->boolean('submit_to_meta'));

        return redirect()->route('user.message-templates.index', ['provider' => $request->validated('provider') ?? 'whatsapp'])->with('status', 'Template saved.');
    }

    public function generate(Request $request, MessageTemplateAiGeneratorService $generator, MessageTemplateService $service, WorkspaceResolver $workspaces, PlanLimitService $limits): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:whatsapp,telegram'],
            'language' => ['nullable', 'string', 'max:16'],
            'category' => ['nullable', 'in:marketing,utility,authentication'],
            'prompt' => ['required', 'string', 'max:1000'],
        ]);

        $workspace = $workspaces->current($request->user());
        $limits->ensurePlatformAiCredits($workspace->id);

        $provider = $validated['provider'];
        $language = $validated['language'] ?? ($provider === 'whatsapp' ? 'en_US' : 'en');
        $category = $provider === 'whatsapp' ? ($validated['category'] ?? 'marketing') : 'utility';
        $draft = $generator->generate([
            'provider' => $provider,
            'language' => $language,
            'category' => $category,
            'instruction' => $validated['prompt'],
        ]);

        $template = $service->store($request->user(), [
            'provider' => $provider,
            'name' => $service->uniqueNameForUser($request->user(), $provider, $language, $draft['name'] ?? 'ai_generated_template'),
            'language' => $language,
            'category' => $draft['category'] ?? $category,
            'header' => $draft['header'] ?? ['type' => 'none'],
            'body' => $draft['body'],
            'body_examples' => $draft['body_examples'] ?? [],
            'footer' => $draft['footer'] ?? ['text' => ''],
            'buttons' => $draft['buttons'] ?? [],
        ]);

        $limits->consumePlatformAiCredits($workspace->id);

        return response()->json([
            'template_id' => $template->id,
            'redirect_url' => route('user.message-templates.edit', $template),
            'name' => $template->name,
            'provider' => $draft['provider'],
            'model' => $draft['model'],
        ]);
    }

    public function edit(Request $request, MessageTemplate $template, MessageTemplateService $service): View
    {
        $template = $service->templateForUser($request->user(), $template);

        return view('message-templates::user.create', [
            'provider' => $template->provider,
            'wabas' => $service->wabasForUser($request->user()),
            'template' => $template,
        ]);
    }

    public function update(StoreMessageTemplateRequest $request, MessageTemplate $template, MessageTemplateService $service): RedirectResponse
    {
        $service->update($request->user(), $template, $request->validated(), $request->boolean('submit_to_meta'));

        return redirect()->route('user.message-templates.index', ['provider' => $request->validated('provider') ?? $template->provider])->with('status', 'Template updated.');
    }

    public function destroy(Request $request, MessageTemplate $template, MessageTemplateService $service): RedirectResponse
    {
        $service->delete($request->user(), $template);

        return back()->with('status', 'Template deleted.');
    }

    public function submit(SubmitMessageTemplateRequest $request, MessageTemplate $template, MessageTemplateService $service): RedirectResponse
    {
        $result = $service->submit($request->user(), $template, $request->validated('provider_account_id'));

        if ($result['ok']) {
            return back()->with('status', 'Template submitted to Meta.');
        }

        $message = $service->metaErrorMessage($result['response'] ?? [])
            ?: 'Meta did not return a detailed reason. Check your WhatsApp credentials and template content.';

        return back()
            ->with('status', 'Template submit failed: '.$message)
            ->with('error', $message);
    }

    public function sync(Request $request, MessageTemplateService $service): RedirectResponse
    {
        $result = $service->sync($request->user());

        return back()->with('status', $result['ok'] ? "Synced {$result['synced']} template(s)." : 'Template sync failed.');
    }
}
