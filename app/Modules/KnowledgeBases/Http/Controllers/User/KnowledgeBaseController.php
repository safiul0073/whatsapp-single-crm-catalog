<?php

namespace App\Modules\KnowledgeBases\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Modules\KnowledgeBases\Http\Requests\StoreKnowledgeBaseRequest;
use App\Modules\KnowledgeBases\Http\Requests\StoreKnowledgeBaseSourceRequest;
use App\Modules\KnowledgeBases\Models\KnowledgeBase;
use App\Modules\KnowledgeBases\Models\KnowledgeBaseSource;
use App\Modules\KnowledgeBases\Services\KnowledgeBaseService;
use App\Modules\KnowledgeBases\Services\QdrantVectorStoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request, KnowledgeBaseService $knowledgeBases, QdrantVectorStoreService $vectors): View
    {
        return view('knowledge-bases::user.index', [
            'knowledgeBases' => $knowledgeBases->listForUser($request->user(), $request->only(['status', 'q'])),
            'stats' => $knowledgeBases->statsForUser($request->user()),
            'vectorSearchEnabled' => $vectors->enabled(),
            'filters' => [
                'status' => $request->query('status', 'all'),
                'q' => $request->query('q', ''),
            ],
        ]);
    }

    public function store(StoreKnowledgeBaseRequest $request, KnowledgeBaseService $knowledgeBases): RedirectResponse
    {
        $knowledgeBase = $knowledgeBases->create($request->user(), $request->validated());

        return redirect()->route('user.knowledge-bases.show', $knowledgeBase)->with('status', 'Knowledge base created.');
    }

    public function show(Request $request, KnowledgeBase $knowledgeBase, KnowledgeBaseService $knowledgeBases, QdrantVectorStoreService $vectors): View
    {
        $knowledgeBase = $knowledgeBases->forUser($request->user(), $knowledgeBase)
            ->load(['sources.chunks', 'chatbots']);

        return view('knowledge-bases::user.show', [
            'knowledgeBase' => $knowledgeBase,
            'sources' => $knowledgeBase->sources()->withCount('chunks')->latest()->paginate(10),
            'chunks' => $knowledgeBase->chunks()->with('source')->latest()->limit(20)->get(),
            'vectorSearchEnabled' => $vectors->enabled(),
        ]);
    }

    public function update(StoreKnowledgeBaseRequest $request, KnowledgeBase $knowledgeBase, KnowledgeBaseService $knowledgeBases): RedirectResponse
    {
        $knowledgeBases->update($request->user(), $knowledgeBase, $request->validated());

        return back()->with('status', 'Knowledge base updated.');
    }

    public function destroy(Request $request, KnowledgeBase $knowledgeBase, KnowledgeBaseService $knowledgeBases): RedirectResponse
    {
        $knowledgeBases->delete($request->user(), $knowledgeBase);

        return redirect()->route('user.knowledge-bases.index')->with('status', 'Knowledge base deleted.');
    }

    public function storeSource(StoreKnowledgeBaseSourceRequest $request, KnowledgeBase $knowledgeBase, KnowledgeBaseService $knowledgeBases): RedirectResponse
    {
        $knowledgeBases->addSource($request->user(), $knowledgeBase, $request->validated(), $request->file('file'));

        return redirect()->route('user.knowledge-bases.show', $knowledgeBase)->with('status', 'Source queued for indexing.');
    }

    public function reindexSource(Request $request, KnowledgeBaseSource $source, KnowledgeBaseService $knowledgeBases): RedirectResponse
    {
        $source = $knowledgeBases->reindexSource($request->user(), $source);

        return redirect()->route('user.knowledge-bases.show', $source->knowledge_base_id)->with('status', 'Source queued for re-indexing.');
    }

    public function destroySource(Request $request, KnowledgeBaseSource $source, KnowledgeBaseService $knowledgeBases): RedirectResponse
    {
        $knowledgeBaseId = $source->knowledge_base_id;
        $knowledgeBases->deleteSource($request->user(), $source);

        return redirect()->route('user.knowledge-bases.show', $knowledgeBaseId)->with('status', 'Source deleted.');
    }
}
