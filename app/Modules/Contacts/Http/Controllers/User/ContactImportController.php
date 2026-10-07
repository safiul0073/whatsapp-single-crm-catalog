<?php

namespace App\Modules\Contacts\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Http\Requests\ImportCsvRequest;
use App\Modules\Contacts\Models\ContactImport;
use App\Modules\Contacts\Services\ContactFileReader;
use App\Modules\Contacts\Services\ContactImportService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContactImportController extends Controller
{
    public function upload(ImportCsvRequest $request, ContactImportService $service): JsonResponse
    {
        $result = $service->parse($request->user(), $request->validated());
        $isWaitingForSheet = ! empty($result['sheets']) && count($result['sheets']) > 1 && $request->validated('sheet') === null;
        $result['auto_started'] = false;

        if (! $isWaitingForSheet && $service->canAutoStart($result['import']->column_mapping ?? [])) {
            $service->process($result['import']->id);
            $result['auto_started'] = true;
        }

        return response()->json($result);
    }

    public function process(Request $request, ContactImportService $service, string $import): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'column_mapping' => ['nullable', 'array'],
            'column_mapping.*' => ['nullable', 'string'],
        ]);

        $service->process((int) $import, $validated['column_mapping'] ?? null, $request->user());

        if ($request->wantsJson()) {
            return response()->json(['status' => 'processing']);
        }

        return back()->with('status', 'Import started.');
    }

    public function show(Request $request, ContactImportService $service, string $import): JsonResponse
    {
        return response()->json($service->show((int) $import, $request->user()));
    }

    public function history(Request $request, WorkspaceResolver $workspaces): JsonResponse
    {
        $workspace = $workspaces->current($request->user());

        $imports = ContactImport::query()
            ->where('workspace_id', $workspace->id)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (ContactImport $import) => [
                'id' => $import->id,
                'file_name' => $import->file_name,
                'status' => $import->status->value,
                'created_rows' => $import->created_rows,
                'updated_rows' => $import->updated_rows,
                'failed_rows' => $import->failed_rows,
                'created_at_diff' => $import->created_at->diffForHumans(),
            ]);

        return response()->json($imports);
    }

    public function sheets(Request $request, ContactFileReader $reader): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
        ]);

        $file = $request->file('file');
        $path = $file->store('imports/temp');
        $fullPath = Storage::disk('local')->path($path);

        try {
            $sheets = $reader->sheets($fullPath);
        } finally {
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        return response()->json(['sheets' => $sheets]);
    }
}
