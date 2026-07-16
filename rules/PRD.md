Since you are using **Laravel Blade + Alpine.js** (without Livewire), the industry-standard architecture for high-performance, reusable tables is the **"AJAX HTML Fragment Swapping"** pattern.

This is how modern frameworks (like GitHub's internal tools or Hotwire) work: The initial page loads normally, but searching, sorting, and pagination are handled by Alpine.js fetching **HTML partials** from the server, not JSON. This keeps your logic in PHP while giving the user an "app-like" feel.

Here is the robust, high-performance setup.

---

### The Architecture: 3-Step Setup

1. **The Builder (PHP):** A helper class to define columns and query logic centrally.
2. **The Component (Blade/Alpine):** A single generic Blade file that handles the AJAX requests.
3. **The Controller:** Handles both the initial page load and the AJAX partial updates.

### Step 1: Create the Reusable Table Class

Do not write column definitions in the view. Create a simple class to manage them.

**File:** `app/Services/Table/TableConfig.php`

```php
namespace App\Services\Table;

class TableConfig
{
    public static function build($query, $columns)
    {
        $sortField = request('sort', 'id');
        $sortDirection = request('direction', 'desc');
        $search = request('search');

        // 1. Handle Search (Basic Example)
        if ($search) {
            $query->where(function($q) use ($columns, $search) {
                foreach ($columns as $col) {
                    if ($col['searchable'] ?? false) {
                        $q->orWhere($col['field'], 'like', $search . '%'); // Prefix search is faster
                    }
                }
            });
        }

        // 2. Handle Sort
        $query->orderBy($sortField, $sortDirection);

        // 3. Handle Pagination (High Data Optimized)
        // Use cursorPaginate for millions of rows to avoid "OFFSET" slowness
        return $query->cursorPaginate(request('per_page', 15));
    }
}

```

### Step 2: The "One-Click" Controller Setup

Your controller needs to handle two states: the full page load (first visit) and the partial load (when searching/sorting).

**File:** `app/Http/Controllers/UserController.php`

```php
public function index(Request $request)
{
    // 1. Define Columns (Configuration)
    $columns = [
        ['label' => 'ID', 'field' => 'id', 'sortable' => true],
        ['label' => 'Name', 'field' => 'name', 'sortable' => true, 'searchable' => true],
        ['label' => 'Email', 'field' => 'email', 'sortable' => true, 'searchable' => true],
        ['label' => 'Date', 'field' => 'created_at', 'sortable' => true],
    ];

    // 2. Get Data via Helper
    $data = TableConfig::build(User::query(), $columns);

    // 3. RETURN: If AJAX, return ONLY the table rows (Performance)
    if ($request->ajax()) {
        return view('components.table.rows', compact('data', 'columns'))->render();
    }

    // 4. RETURN: Full page setup
    return view('users.index', compact('data', 'columns'));
}

```

### Step 3: The Reusable Blade + Alpine Component

This is the heart of the system. It uses Alpine to intercept clicks and fetch data.

**File:** `resources/views/components/table/wrapper.blade.php`

```html
@props(['route', 'columns', 'data'])

<div x-data="tableManager('{{ $route }}')" class="bg-white shadow rounded-lg">
    
    <div class="p-4 flex justify-between items-center border-b">
        <input 
            type="text" 
            x-model.debounce.500ms="params.search" 
            placeholder="Search..." 
            class="border rounded px-3 py-2 w-64"
        >
        <span x-show="loading" class="text-gray-500 text-sm">Loading...</span>
    </div>

    <div class="overflow-x-auto relative">
        <table class="w-full text-left">
            <thead class="bg-gray-50">
                <tr>
                    @foreach($columns as $col)
                        <th class="p-3 font-semibold text-gray-700">
                            @if($col['sortable'] ?? false)
                                <button @click="sortBy('{{ $col['field'] }}')" class="flex items-center hover:text-blue-600">
                                    {{ $col['label'] }}
                                    <span x-show="params.sort === '{{ $col['field'] }}'" class="ml-1">
                                        <span x-show="params.direction === 'asc'">↑</span>
                                        <span x-show="params.direction === 'desc'">↓</span>
                                    </span>
                                </button>
                            @else
                                {{ $col['label'] }}
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            
            <tbody x-ref="tbody" class="divide-y divide-gray-200">
                @include('components.table.rows', ['data' => $data, 'columns' => $columns])
            </tbody>
        </table>
    </div>

    <div class="p-4 border-t" x-ref="pagination">
        {{ $data->links() }} 
        </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('tableManager', (route) => ({
            loading: false,
            params: {
                search: '',
                sort: 'id',
                direction: 'desc',
                page: 1
            },
            
            init() {
                // Watch for search changes
                this.$watch('params.search', () => {
                    this.params.page = 1; // Reset to page 1 on search
                    this.fetchData();
                });
            },

            sortBy(field) {
                if (this.params.sort === field) {
                    this.params.direction = this.params.direction === 'asc' ? 'desc' : 'asc';
                } else {
                    this.params.sort = field;
                    this.params.direction = 'asc';
                }
                this.fetchData();
            },

            fetchData() {
                this.loading = true;
                
                // Build Query String
                const queryString = new URLSearchParams(this.params).toString();
                const url = `${route}?${queryString}`;

                fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => response.text())
                .then(html => {
                    // Inject the new HTML rows
                    this.$refs.tbody.innerHTML = html;
                    this.loading = false;
                    
                    // Update URL without refresh (Deep Linking)
                    window.history.pushState({}, '', url);
                });
            }
        }));
    });
</script>

```

**File:** `resources/views/components/table/rows.blade.php` (The Partial)

```html
@foreach($data as $row)
    <tr class="hover:bg-gray-50 transition">
        @foreach($columns as $col)
            <td class="p-3">
                {{ $row->{$col['field']} }}
            </td>
        @endforeach
    </tr>
@endforeach

@if($data->isEmpty())
    <tr>
        <td colspan="{{ count($columns) }}" class="p-4 text-center text-gray-500">
            No records found.
        </td>
    </tr>
@endif

```

### Why This is "High Data" Proof

When dealing with millions of rows, the UI doesn't matter as much as the Query Strategy.

1. **Cursor Pagination:**
* Standard `paginate(10)` creates a query like `OFFSET 1000000 LIMIT 10`. The database has to count 1,000,000 rows just to skip them.
* **Cursor Pagination** uses `WHERE id > 1000000 LIMIT 10`. It is instant, even with 50 million rows.
* *Implementation:* In the `TableConfig` class I provided, ensure you use `cursorPaginate()`.


2. **AJAX vs DOM Bloat:**
* This setup only replaces the `<tbody>`. It does not re-render the header, sidebar, or footer. This is memory efficient for the browser.


3. **Debounced Search:**
* The `.debounce.500ms` in the Alpine input is critical. It prevents sending a database query for every keystroke, which would crash a high-traffic server.



### How to Implement in "One Click"

Now, whenever you need a table on a new page:

1. **Controller:** Copy-paste the `index` method and change `$columns`.
2. **View:** Just add one line:

```html
<x-table.wrapper :route="route('orders.index')" :columns="$columns" :data="$data" />

```

Would you like me to provide the specific **database migration code** for adding the necessary **Composite Indexes** to make the search and sort instant on high-volume data?
