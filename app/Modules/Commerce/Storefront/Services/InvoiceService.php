<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Models\Order;

class InvoiceService
{
    public function pdf(Order $order): string
    {
        $order->loadMissing('items');
        $lines = ['INVOICE '.$order->number, 'Date: '.$order->created_at->format('Y-m-d'), 'Status: '.$order->status, 'Customer: '.($order->customer_snapshot['name'] ?? ''), ''];
        foreach ($order->items as $item) {
            foreach (explode("\n", wordwrap($item->product_name.' | '.$item->quantity.' x '.$item->unit_price.' = '.$item->line_total, 85, "\n", true)) as $line) {
                $lines[] = $line;
            }
        }
        $lines = array_merge($lines, ['', 'Subtotal: '.$order->subtotal, 'Discount: '.$order->discount_amount, 'Shipping: '.$order->shipping_amount, 'Total: '.$order->total.' '.$order->currency, 'Payment: '.$order->payment_state]);
        $chunks = array_chunk($lines, 45);
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
        $kids = [];
        foreach ($chunks as $index => $page) {
            $id = 4 + $index * 2;
            $stream = "BT /F1 11 Tf 50 790 Td 16 TL\n";
            foreach ($page as $line) {
                $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $line) ?: '';
                $text = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $text);
                $stream .= '('.$text.") Tj T*\n";
            }
            $stream .= 'ET';
            $objects[$id] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.($id + 1).' 0 R >>';
            $objects[$id + 1] = '<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream";
            $kids[] = $id.' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($objects as $id => $object) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$id])."\n";
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    }
}
