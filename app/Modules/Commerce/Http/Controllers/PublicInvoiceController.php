<?php

namespace App\Modules\Commerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Storefront\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicInvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoices) {}

    /**
     * Serves the invoice PDF for the private link sent to the customer.
     */
    public function show(Request $request, string $token): Response
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{40}$/', $token) === 1, 404);

        $order = Order::query()->where('invoice_token', $token)->firstOrFail();
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($this->invoices->pdf($order), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$order->invoice_number.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
