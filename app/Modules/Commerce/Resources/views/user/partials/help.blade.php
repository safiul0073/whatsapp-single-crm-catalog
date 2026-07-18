@php
    $topics = [
        'channel' => [
            'title' => __('Channel setup help'),
            'summary' => __('Connect a verified WhatsApp sender before configuring your store or sending products.'),
            'icon' => 'ph-plugs-connected',
            'steps' => [
                __('Choose WhatsApp Cloud and complete Meta Embedded Signup or enter the required business credentials.'),
                __('Confirm the phone number ID, WhatsApp Business Account ID, access token, and webhook verification.'),
                __('Use the connection test and resolve token or webhook errors before continuing.'),
                __('Open Meta Catalog after the channel status becomes Connected.'),
            ],
            'tip' => __('Use a permanent system-user token in production and never share it with customers.'),
            'nextLabel' => __('Open Meta Catalog'),
            'nextUrl' => route('user.commerce.catalog'),
        ],
        'products' => [
            'title' => __('Product management help'),
            'summary' => __('Review product readiness, inventory, and publication status before sending samples to buyers.'),
            'icon' => 'ph-t-shirt',
            'steps' => [
                __('Use Table view for operational details or Grid view for visual browsing.'),
                __('Open a product to complete details, gallery, options, variants, and readiness review.'),
                __('Publish only products with an HTTPS primary image, valid SKU, retailer ID, price, and availability.'),
                __('Synchronize the product with Meta Catalog before sending it from Inbox.'),
            ],
            'tip' => __('A published local product is not sendable until its Meta catalog item is synchronized successfully.'),
            'nextLabel' => __('Create product'),
            'nextUrl' => route('user.commerce.products.create'),
        ],
        'product_form' => [
            'title' => __('Product wizard help'),
            'summary' => __('Complete each saved step to produce a reliable WhatsApp-ready garment listing.'),
            'icon' => 'ph-note-pencil',
            'steps' => [
                __('Details: select category, brand, audience, origin, condition, and customer-facing information.'),
                __('Gallery: select up to ten images and one MP4 video; mark one image as primary.'),
                __('Options: add size, color, material, fit, or pattern values.'),
                __('Variants: verify every SKU, Meta retailer ID, USD price, stock quantity, weight, and image.'),
                __('Review: resolve all readiness warnings, publish, and synchronize the Meta catalog.'),
            ],
            'tip' => __('Video is supplemental: send it separately from Inbox because WhatsApp product cards use catalog images.'),
            'nextLabel' => __('Back to products'),
            'nextUrl' => route('user.commerce.products.index'),
        ],
        'categories' => [
            'title' => __('Category help'),
            'summary' => __('Use categories to organize garments and make product assignment easier for your team.'),
            'icon' => 'ph-tree-structure',
            'steps' => [
                __('Create a main category such as Shirts, Dresses, Jackets, or Uniforms.'),
                __('Optionally select a parent to create a child category.'),
                __('Keep active categories available for product assignment.'),
                __('Move products and child categories before deleting a category.'),
            ],
            'tip' => __('Categories organize your local workspace; Meta item grouping is controlled by product variants and retailer IDs.'),
            'nextLabel' => __('Manage brands'),
            'nextUrl' => route('user.commerce.brands.index'),
        ],
        'brands' => [
            'title' => __('Brand help'),
            'summary' => __('Create approved brand names here before assigning them to products.'),
            'icon' => 'ph-seal-check',
            'steps' => [
                __('Create each brand once using the exact customer-facing spelling.'),
                __('Disable a brand to stop new assignments without changing existing product history.'),
                __('Renaming a brand updates the compatibility value used by product feeds.'),
                __('Move assigned products before deleting a brand.'),
            ],
            'tip' => __('Use your real manufacturer or private-label brand name; avoid names you are not authorized to sell.'),
            'nextLabel' => __('Manage audiences'),
            'nextUrl' => route('user.commerce.audiences.index'),
        ],
        'audiences' => [
            'title' => __('Audience help'),
            'summary' => __('Define reusable buyer groups such as Women, Men, Unisex, Kids, Teen, or Baby.'),
            'icon' => 'ph-users-three',
            'steps' => [
                __('Create the audience labels required by your garment range.'),
                __('Assign one audience to each product in the Details step.'),
                __('Disable an audience when it should no longer be assigned.'),
                __('Move assigned products before deleting an audience.'),
            ],
            'tip' => __('Use consistent audience names because they are also included in Meta garment attributes.'),
            'nextLabel' => __('Create product'),
            'nextUrl' => route('user.commerce.products.create'),
        ],
        'catalog' => [
            'title' => __('Meta Catalog help'),
            'summary' => __('Connect the WhatsApp channel to an existing Meta catalog and keep every item synchronized.'),
            'icon' => 'ph-storefront',
            'steps' => [
                __('Select a connected WhatsApp channel and enter the catalog ID from Meta Commerce Manager.'),
                __('Choose Scheduled Feed for simple reliability or Direct API when token capability checks pass.'),
                __('Resolve HTTPS URL, image, SKU, retailer ID, token, and rejected-item diagnostics.'),
                __('Enable catalog visibility and cart settings after access is verified.'),
                __('Synchronize or validate the feed, then confirm products report a successful status.'),
            ],
            'tip' => __('Your production application and all catalog images must be publicly reachable over HTTPS.'),
            'nextLabel' => __('Open Inbox'),
            'nextUrl' => route('user.inbox.index'),
        ],
        'inbox' => [
            'title' => __('Send product samples'),
            'summary' => __('Use an active WhatsApp conversation to send a catalog, one product, a selection, or a video.'),
            'icon' => 'ph-paper-plane-tilt',
            'steps' => [
                __('Ask the consenting buyer to message your WhatsApp number so the 24-hour service window opens.'),
                __('Select the buyer conversation and confirm the WhatsApp channel is connected.'),
                __('Click the storefront icon in the conversation header to open Send products.'),
                __('Send the complete catalog, one synchronized variant, or a curated multi-product selection.'),
                __('Send gallery video separately with an optional caption.'),
                __('When the buyer submits the WhatsApp cart, continue in Commerce Orders.'),
            ],
            'tip' => __('If the 24-hour window has expired, send an approved template and wait for the buyer to reply. Only the buyer’s inbound message reopens interactive product messaging.'),
            'nextLabel' => __('View orders'),
            'nextUrl' => route('user.commerce.orders.index'),
        ],
        'orders' => [
            'title' => __('Commerce orders help'),
            'summary' => __('Review incoming WhatsApp carts, resolve item issues, and prepare manual US shipping quotes.'),
            'icon' => 'ph-package',
            'steps' => [
                __('Open new requested orders and review quantities, prices, and catalog warnings.'),
                __('Collect the buyer name, phone, US address, state, ZIP code, and delivery notes.'),
                __('Enter shipping, duties disclosure, delivery method, and an HTTPS payment link.'),
                __('Move the order through the controlled workflow as payment, processing, and shipping are confirmed.'),
            ],
            'tip' => __('Stock is deducted exactly once when the order is marked Paid—not when the buyer first submits a cart.'),
            'nextLabel' => __('View products'),
            'nextUrl' => route('user.commerce.products.index'),
        ],
        'order' => [
            'title' => __('Order review help'),
            'summary' => __('Validate the immutable cart snapshot, prepare the quote, and use controlled status transitions.'),
            'icon' => 'ph-clipboard-text',
            'steps' => [
                __('Resolve unknown, inactive, currency-mismatched, or stale-price items before quoting.'),
                __('Validate the complete US delivery address and manually calculate international shipping.'),
                __('Provide only a secure HTTPS external payment URL; never store card or banking credentials.'),
                __('Mark Paid only after payment confirmation, then add tracking when the shipment is dispatched.'),
            ],
            'tip' => __('Cancelling a paid but unshipped order restores its deducted inventory exactly once.'),
            'nextLabel' => __('Back to orders'),
            'nextUrl' => route('user.commerce.orders.index'),
        ],
    ];
    $topic = $topics[$helpKey] ?? $topics['products'];
    $modalId = 'commerceHelp'.str($helpKey)->studly()->toString();
@endphp

@if ($minimal ?? false)
    <div class="mb-4 rounded-lg border border-neutral-200 bg-section p-3" data-commerce-help="{{ $helpKey }}">
        <div class="flex items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-2.5">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary"><i class="ph {{ $topic['icon'] }} text-base"></i></span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-title">{{ $topic['title'] }}</p>
                    <p class="truncate text-xs text-body">{{ __('Send catalog or products from WhatsApp.') }}</p>
                </div>
            </div>
            <button type="button" class="row-action shrink-0" data-modal-open="{{ $modalId }}" aria-label="{{ __('Open help') }}">
                <i class="ph ph-question text-base"></i>
            </button>
        </div>
    </div>
@else
    <div class="flex flex-col gap-3 rounded-xl bg-primary/5 px-4 py-3 {{ ($compact ?? false) ? '' : 'sm:flex-row sm:items-center sm:justify-between' }}" data-commerce-help="{{ $helpKey }}">
        <div class="flex min-w-0 items-start gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary/10 text-primary"><i class="ph {{ $topic['icon'] }} text-lg"></i></span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-title">{{ $topic['title'] }}</p>
                <p class="text-xs leading-5 text-body">{{ $topic['summary'] }}</p>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline shrink-0 {{ ($compact ?? false) ? 'w-full justify-center' : '' }}" data-modal-open="{{ $modalId }}">
            <i class="ph ph-question text-base"></i> {{ __('Help') }}
        </button>
    </div>
@endif

@push('modals')
    <div class="modal" id="{{ $modalId }}" data-modal>
        <div class="modal__backdrop" data-modal-close></div>
        <div class="modal__panel max-w-2xl" role="dialog" aria-modal="true" aria-labelledby="{{ $modalId }}Title">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary"><i class="ph {{ $topic['icon'] }} text-xl"></i></span>
                    <div><p class="text-sm font-semibold text-primary">{{ __('Contextual help') }}</p><h2 id="{{ $modalId }}Title" class="heading-4 text-title">{{ $topic['title'] }}</h2></div>
                </div>
                <button type="button" class="row-action" data-modal-close aria-label="{{ __('Close help') }}"><i class="ph ph-x text-lg"></i></button>
            </div>

            <p class="mt-4 text-sm leading-6 text-body">{{ $topic['summary'] }}</p>
            <ol class="mt-5 space-y-3">
                @foreach ($topic['steps'] as $step)
                    <li class="flex items-start gap-3">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-primary text-xs font-bold text-neutral-0">{{ $loop->iteration }}</span>
                        <p class="pt-0.5 text-sm leading-6 text-title">{{ $step }}</p>
                    </li>
                @endforeach
            </ol>

            <div class="mt-5 rounded-xl bg-warning/10 p-4">
                <div class="flex items-start gap-3"><i class="ph ph-lightbulb mt-0.5 text-warning"></i><div><p class="text-sm font-semibold text-title">{{ __('Important') }}</p><p class="mt-1 text-sm leading-6 text-body">{{ $topic['tip'] }}</p></div></div>
            </div>

            <div class="mt-6 border-t border-border-soft pt-5">
                <h3 class="text-sm font-semibold text-title">{{ __('Complete WhatsApp selling workflow') }}</h3>
                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                    @foreach ([['ph-plugs-connected', __('Connect channel')], ['ph-storefront', __('Sync catalog')], ['ph-check-circle', __('Publish product')], ['ph-clock-countdown', __('Open 24-hour session')], ['ph-paper-plane-tilt', __('Send samples')], ['ph-package', __('Quote and fulfill')]] as [$icon, $label])
                        <div class="flex items-center gap-2 rounded-lg bg-section px-3 py-2 text-xs font-semibold text-title"><i class="ph {{ $icon }} text-primary"></i>{{ $label }}</div>
                    @endforeach
                </div>
            </div>

            <div class="mt-6 flex flex-wrap justify-end gap-2">
                <button type="button" class="btn btn-outline" data-modal-close>{{ __('Close') }}</button>
                <a href="{{ $topic['nextUrl'] }}" class="btn btn-primary">{{ $topic['nextLabel'] }} <i class="ph ph-arrow-right"></i></a>
            </div>
        </div>
    </div>
@endpush
