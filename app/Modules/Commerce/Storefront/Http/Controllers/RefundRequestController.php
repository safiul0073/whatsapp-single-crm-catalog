<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Commerce\Storefront\Http\Requests\RefundRequestRequest;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\RefundRequest;
use App\Modules\Commerce\Storefront\Models\RefundRequestEvent;
use App\Modules\Commerce\Storefront\Services\RefundRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class RefundRequestController extends Controller
{
    public function __construct(private RefundRequestService $refunds) {}

    /**
     * The customer's own refund requests.
     */
    public function index(RefundRequestRequest $r): JsonResponse
    {
        $c = $this->customer($r);

        return $this->page($this->own($c)->with(['order', 'events'])->latest('id'), false);
    }

    public function show(RefundRequestRequest $r, string $ref): JsonResponse
    {
        $c = $this->customer($r);

        return response()->json(['data' => $this->payload($this->own($c)->where('reference', $ref)->with(['order', 'events'])->firstOrFail(), false)]);
    }

    /**
     * How much of an order can still be refunded.
     */
    public function eligibility(RefundRequestRequest $r, string $ref): JsonResponse
    {
        $c = $this->customer($r);
        $order = Order::query()->where('workspace_id', $c->workspace_id)->where('contact_id', $c->contact_id)->where('submission_reference', $ref)->firstOrFail();
        $open = RefundRequest::query()->where('workspace_id', $c->workspace_id)->where('order_id', $order->id)->whereIn('status', RefundRequest::OPEN_STATUSES)->first();
        $refundable = $open ? 0 : $this->refunds->refundableMinor($order);

        return response()->json(['data' => [
            'eligible' => $refundable > 0,
            'refundable' => OrderMoney::decimal($refundable, $order->moneyPrecision()),
            'currency' => $order->currency,
            'open_request' => $open?->reference,
            'reasons' => RefundRequest::REASONS,
        ]]);
    }

    public function store(RefundRequestRequest $r): JsonResponse
    {
        $key = (string) $r->header('Idempotency-Key');
        abort_unless(Str::isUuid($key), 422, 'Idempotency-Key must be a UUID.');
        $c = $this->customer($r);
        $order = Order::query()->where('workspace_id', $c->workspace_id)->where('contact_id', $c->contact_id)->where('submission_reference', $r->validated('order'))->firstOrFail();
        $request = $this->refunds->submit($c, $order, $r->safe()->only(['amount', 'reason', 'details']), $key);

        return response()->json(['data' => $this->payload($request->load(['order', 'events']), false)], $request->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Refund requests from customers this customer referred.
     */
    public function review(RefundRequestRequest $r): JsonResponse
    {
        $c = $this->customer($r);
        $status = (string) $r->query('status');

        return $this->page($this->referred($c)->when($status === 'pending', fn (Builder $q) => $q->where('status', 'pending_referrer'))->when($status === 'reviewed', fn (Builder $q) => $q->where('status', '!=', 'pending_referrer'))->with(['order.items', 'customer.contact', 'events'])->latest('id'), true);
    }

    public function reviewShow(RefundRequestRequest $r, string $ref): JsonResponse
    {
        $c = $this->customer($r);

        return response()->json(['data' => $this->payload($this->referred($c)->where('reference', $ref)->with(['order.items', 'customer.contact', 'events'])->firstOrFail(), true)]);
    }

    public function decide(RefundRequestRequest $r, string $ref): JsonResponse
    {
        $request = $this->refunds->referrerDecide($this->customer($r), $ref, $r->validated('decision'), $r->validated('comment'));

        return response()->json(['data' => $this->payload($request->load(['order.items', 'customer.contact', 'events']), true)]);
    }

    private function customer(RefundRequestRequest $r): Customer
    {
        $c = $r->attributes->get('store_customer');
        abort_unless($this->refunds->enabled($c->workspace_id), 404);

        return $c;
    }

    private function own(Customer $c): Builder
    {
        return RefundRequest::query()->where('workspace_id', $c->workspace_id)->where('customer_id', $c->id);
    }

    private function referred(Customer $c): Builder
    {
        return RefundRequest::query()->where('workspace_id', $c->workspace_id)->where('referrer_customer_id', $c->id);
    }

    private function page(Builder $query, bool $forReferrer): JsonResponse
    {
        $page = $query->paginate(20);

        return response()->json([
            'data' => collect($page->items())->map(fn (RefundRequest $request): array => $this->payload($request, $forReferrer))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(RefundRequest $request, bool $forReferrer): array
    {
        $order = $request->order;
        $precision = $order->moneyPrecision();
        $data = [
            'reference' => $request->reference,
            'status' => $request->status,
            'amount' => OrderMoney::decimal($request->amount_minor, $precision),
            'currency' => $request->currency,
            'reason' => $request->reason,
            'details' => $request->details,
            'referrer_decision' => $request->referrer_decision,
            'referrer_comment' => $request->referrer_comment,
            'admin_note' => $request->admin_note,
            'created_at' => $request->created_at?->toIso8601String(),
            'completed_at' => $request->completed_at?->toIso8601String(),
            'order' => ['number' => $order->number, 'status' => $order->status, 'total' => $order->total, 'placed_at' => $order->created_at?->toIso8601String()],
            'history' => $request->events->map(fn (RefundRequestEvent $e): array => ['actor' => $e->actor_type, 'status' => $e->to_status, 'comment' => $e->comment, 'at' => $e->created_at?->toIso8601String()])->values(),
        ];
        if ($forReferrer) {
            $data['customer'] = ['name' => $request->customer?->contact?->name];
            $data['order']['items'] = $order->items->map(fn ($item): array => ['name' => $item->product_name, 'quantity' => $item->quantity])->values();
            $data['can_decide'] = $request->status === 'pending_referrer';
        } else {
            $data['order']['reference'] = $order->submission_reference;
            $data['payout_method'] = $request->payout_method;
        }

        return $data;
    }
}
