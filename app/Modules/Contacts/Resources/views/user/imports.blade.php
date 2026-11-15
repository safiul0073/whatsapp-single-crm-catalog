<x-layouts.user :title="__('Import History')">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h2 class="heading-2">Import History</h2>
            <p class="m-text mt-1">Review your recent contact imports and their results.</p>
          </div>
          <a href="{{ route('user.contacts.index') }}" class="btn-sm btn-primary">
            <i class="ph ph-upload-simple text-base"></i>
            Import Contacts
          </a>
        </div>

        <div data-table class="mt-6">
          <div class="app-card overflow-hidden">
            <div class="overflow-x-auto">
              <div class="list-table" style="--list-cols: minmax(12rem, 2fr) 7rem 5rem 5rem 5rem minmax(8rem, 1fr);">
                <div class="list-table__head">
                  <span>File</span>
                  <span>Status</span>
                  <span>Created</span>
                  <span>Updated</span>
                  <span>Failed</span>
                  <span>Date</span>
                </div>

                @forelse($imports as $import)
                <div class="list-table__row" data-import>
                  <div class="flex min-w-0 items-center gap-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-primary/10 text-primary">
                      <i class="ph ph-file-csv text-base"></i>
                    </span>
                    <span class="truncate font-semibold text-title">{{ $import->file_name }}</span>
                  </div>
                  <span class="capitalize">{{ str_replace('_', ' ', $import->status->value) }}</span>
                  <span class="font-semibold text-title">{{ $import->created_rows ?? 0 }}</span>
                  <span class="font-semibold text-title">{{ $import->updated_rows ?? 0 }}</span>
                  <span class="font-semibold text-title">{{ $import->failed_rows ?? 0 }}</span>
                  <span title="{{ $import->created_at }}">{{ $import->created_at->diffForHumans() }}</span>
                </div>
                @empty
                <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                  <span class="grid h-12 w-12 place-items-center rounded-md bg-primary/10 text-primary">
                    <i class="ph ph-upload-simple text-2xl"></i>
                  </span>
                  <h3 class="heading-4 mt-4">No imports yet</h3>
                  <p class="m-text mt-1 max-w-sm">Import contacts from a CSV or Excel file to see them here.</p>
                  <a href="{{ route('user.contacts.index') }}" class="btn-sm btn-primary mt-5">Import Contacts</a>
                </div>
                @endforelse
              </div>
            </div>
          </div>

          @if($imports->hasPages())
          <div class="mt-4">
            {{ $imports->links() }}
          </div>
          @endif
        </div>
</x-layouts.user>
