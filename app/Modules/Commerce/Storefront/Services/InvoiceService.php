<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderGroup;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\OrderMoney;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\Workspaces\Models\Workspace;

class InvoiceService
{
    public function pdf(Order $order): string
    {
        $order->loadMissing([
            'groups',
            'items.variant.color.swatchMedia',
            'items.variant.media',
            'items.variant.product.primaryMedia',
            'items.variant.product.gallery.media',
        ]);

        $precision = (new StoreOrderSetting(['currency' => $order->currency]))->precision();
        $currency = (string) ($order->currency ?: 'USD');
        $fmt = fn ($amount): string => $amount !== null
            ? OrderMoney::decimal(OrderMoney::minor($amount, $precision), $precision).' '.$currency
            : '0.00 '.$currency;

        $storeSetting = StorefrontSetting::query()->where('workspace_id', $order->workspace_id)->first();
        $storeName = (string) ($storeSetting?->config['name'] ?? Workspace::query()->find($order->workspace_id)?->name ?? 'Storefront');

        $rows = $this->buildInvoiceRows($order, $precision, $currency);
        $rowChunks = $this->paginateRows($rows);

        $totalPages = max(1, count($rowChunks));

        // Scale and embed unique thumbnail images
        $imageCache = [];
        $imageUrlToName = [];
        $imageResources = [];
        $imageObjects = [];
        $nextObjId = 5 + $totalPages * 2;
        $imgCounter = 1;

        foreach ($rows as $row) {
            $url = $row['image_url'] ?? null;
            if ($url && ! array_key_exists($url, $imageCache)) {
                $scaled = $this->loadAndScaleImage($url);
                $imageCache[$url] = $scaled;
                if ($scaled) {
                    $name = 'Im'.$imgCounter++;
                    $objId = $nextObjId++;
                    $imageUrlToName[$url] = $name;
                    $imageResources[$name] = $objId.' 0 R';
                    $imageObjects[$objId] = '<< /Type /XObject /Subtype /Image /Width '.$scaled['width'].' /Height '.$scaled['height'].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($scaled['data']).">>\nstream\n".$scaled['data']."\nendstream";
                }
            }
        }

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];
        $kids = [];

        $xObjectDict = '';
        if (! empty($imageResources)) {
            $xEntries = [];
            foreach ($imageResources as $name => $ref) {
                $xEntries[] = '/'.$name.' '.$ref;
            }
            $xObjectDict = ' /XObject << '.implode(' ', $xEntries).' >>';
        }

        foreach ($rowChunks as $pageIndex => $pageRows) {
            $pageObjId = 5 + $pageIndex * 2;
            $contentObjId = $pageObjId + 1;
            $isFirstPage = $pageIndex === 0;
            $isLastPage = $pageIndex === ($totalPages - 1);

            $stream = $this->renderPageStream(
                order: $order,
                storeName: $storeName,
                rows: $pageRows,
                fmt: $fmt,
                isFirstPage: $isFirstPage,
                isLastPage: $isLastPage,
                pageNum: $pageIndex + 1,
                totalPages: $totalPages,
                imageUrlToName: $imageUrlToName
            );

            $objects[$pageObjId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>'.$xObjectDict.' >> /Contents '.$contentObjId.' 0 R >>';
            $objects[$contentObjId] = '<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream";
            $kids[] = $pageObjId.' 0 R';
        }

        foreach ($imageObjects as $id => $objStr) {
            $objects[$id] = $objStr;
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

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<int, mixed>>
     */
    private function paginateRows(array $rows): array
    {
        $totalRows = count($rows);
        if ($totalRows === 0) {
            return [[]];
        }

        $singlePageMaxRows = 8;
        if ($totalRows <= $singlePageMaxRows) {
            return [$rows];
        }

        $page1MaxRows = 8;
        $otherPageMaxRows = 9;

        $pages = [];
        $pages[] = array_slice($rows, 0, $page1MaxRows);
        $remaining = array_slice($rows, $page1MaxRows);

        while (count($remaining) > 0) {
            $chunk = array_slice($remaining, 0, $otherPageMaxRows);
            $pages[] = $chunk;
            $remaining = array_slice($remaining, count($chunk));
        }

        return $pages;
    }

    /**
     * @return array<int, array{title: string, subtitle: string, breakdown: string, type_label: string, qty: string, unit_price: string, line_total: string, image_url: ?string}>
     */
    private function buildInvoiceRows(Order $order, int $precision, string $currency): array
    {
        $rows = [];
        $itemsByGroup = $order->items->groupBy('group_id');
        $handledItemIds = [];
        $sizeOrder = array_flip(['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL', 'XXXL', '4XL', '5XL']);

        foreach ($order->groups as $group) {
            /** @var OrderGroup $group */
            $groupItems = $itemsByGroup->get($group->id, collect());
            if ($groupItems->isEmpty()) {
                continue;
            }
            foreach ($groupItems as $gi) {
                $handledItemIds[$gi->id] = true;
            }

            if ($group->mode === 'wholesale' || $group->box_count > 0 || $groupItems->count() > 1) {
                $sizes = [];
                foreach ($groupItems as $gi) {
                    /** @var OrderItem $gi */
                    $sz = (string) ($gi->variant?->size ?? $gi->attributes['size'] ?? 'STD');
                    $sizes[$sz] = ($sizes[$sz] ?? 0) + (int) $gi->quantity;
                }
                uksort($sizes, function (string $a, string $b) use ($sizeOrder): int {
                    $orderA = $sizeOrder[strtoupper(trim($a))] ?? 99;
                    $orderB = $sizeOrder[strtoupper(trim($b))] ?? 99;

                    return $orderA !== $orderB ? ($orderA <=> $orderB) : strcasecmp($a, $b);
                });
                $sizeParts = [];
                foreach ($sizes as $sz => $qty) {
                    $sizeParts[] = $sz.': '.$qty;
                }
                $firstItem = $groupItems->first();
                $colorName = $group->color_name
                    ?: ($firstItem?->variant?->color?->display_name ?? $firstItem?->variant?->color?->name ?? ($firstItem?->attributes['color'] ?? 'Assorted'));
                $boxCount = max(1, (int) $group->box_count);
                $totalPcs = (int) ($group->quantity ?: $groupItems->sum('quantity'));
                $pcsPerPack = (int) ($group->pieces_per_box ?: max(1, intdiv($totalPcs, $boxCount)));
                $unitMinor = OrderMoney::minor($firstItem?->unit_price ?? 0, $precision);
                $lineMinor = $groupItems->sum(fn (OrderItem $i): int => OrderMoney::minor($i->line_total, $precision));

                $isCustom = ! empty($group->pack_snapshot);
                $packName = $isCustom ? ($group->pack_snapshot['name'] ?? 'Custom Pack') : null;

                $rows[] = [
                    'title' => $isCustom ? '['.$packName.'] '.(string) $group->product_name : (string) $group->product_name,
                    'subtitle' => ($isCustom ? 'Custom Pack ('.$packName.')' : 'Wholesale Pack').'  |  Color: '.$colorName.'  |  '.$pcsPerPack.' pcs/pack',
                    'breakdown' => 'Size Breakdown: '.implode(', ', $sizeParts),
                    'type_label' => $isCustom ? $boxCount.' Custom Pack(s)' : $boxCount.' Pack(s)',
                    'qty' => $totalPcs.' pcs',
                    'unit_price' => OrderMoney::decimal($unitMinor, $precision).' '.$currency,
                    'line_total' => OrderMoney::decimal($lineMinor, $precision).' '.$currency,
                    'image_url' => $this->resolveItemImageUrl($firstItem),
                ];
            } else {
                foreach ($groupItems as $item) {
                    $rows[] = $this->formatSingleItemRow($item, $precision, $currency);
                }
            }
        }

        $remainingItems = $order->items->reject(fn (OrderItem $i): bool => isset($handledItemIds[$i->id]))->values();
        if ($remainingItems->isNotEmpty()) {
            $byProductColor = $remainingItems->groupBy(function (OrderItem $i): string {
                $color = $i->variant?->color?->display_name ?? $i->variant?->color?->name ?? ($i->attributes['color'] ?? '');

                return $i->product_name.'||'.$color;
            });

            foreach ($byProductColor as $groupedItems) {
                if ($groupedItems->count() > 1) {
                    $firstItem = $groupedItems->first();
                    $colorName = $firstItem->variant?->color?->display_name ?? $firstItem->variant?->color?->name ?? ($firstItem->attributes['color'] ?? 'Pack');
                    $sizes = [];
                    foreach ($groupedItems as $gi) {
                        $sz = (string) ($gi->variant?->size ?? $gi->attributes['size'] ?? 'STD');
                        $sizes[$sz] = ($sizes[$sz] ?? 0) + (int) $gi->quantity;
                    }
                    uksort($sizes, function (string $a, string $b) use ($sizeOrder): int {
                        $orderA = $sizeOrder[strtoupper(trim($a))] ?? 99;
                        $orderB = $sizeOrder[strtoupper(trim($b))] ?? 99;

                        return $orderA !== $orderB ? ($orderA <=> $orderB) : strcasecmp($a, $b);
                    });
                    $sizeParts = [];
                    foreach ($sizes as $sz => $qty) {
                        $sizeParts[] = $sz.': '.$qty;
                    }
                    $totalPcs = (int) $groupedItems->sum('quantity');
                    $unitMinor = OrderMoney::minor($firstItem->unit_price, $precision);
                    $lineMinor = $groupedItems->sum(fn (OrderItem $i): int => OrderMoney::minor($i->line_total, $precision));

                    $rows[] = [
                        'title' => (string) $firstItem->product_name,
                        'subtitle' => 'Wholesale Pack  |  Color: '.$colorName,
                        'breakdown' => 'Size Breakdown: '.implode(', ', $sizeParts),
                        'type_label' => 'Pack',
                        'qty' => $totalPcs.' pcs',
                        'unit_price' => OrderMoney::decimal($unitMinor, $precision).' '.$currency,
                        'line_total' => OrderMoney::decimal($lineMinor, $precision).' '.$currency,
                        'image_url' => $this->resolveItemImageUrl($firstItem),
                    ];
                } else {
                    $rows[] = $this->formatSingleItemRow($groupedItems->first(), $precision, $currency);
                }
            }
        }

        return $rows;
    }

    /**
     * @return array{title: string, subtitle: string, breakdown: string, type_label: string, qty: string, unit_price: string, line_total: string, image_url: ?string}
     */
    private function formatSingleItemRow(OrderItem $item, int $precision, string $currency): array
    {
        $color = $item->variant?->color?->display_name ?? $item->variant?->color?->name ?? ($item->attributes['color'] ?? null);
        $size = $item->variant?->size ?? ($item->attributes['size'] ?? null);
        $meta = [];
        if ($color) {
            $meta[] = 'Color: '.$color;
        }
        if ($size) {
            $meta[] = 'Size: '.$size;
        }
        if ($item->sku) {
            $meta[] = 'SKU: '.$item->sku;
        }
        if (empty($meta)) {
            $meta[] = 'Single Product';
        }

        return [
            'title' => (string) $item->product_name,
            'subtitle' => implode('  |  ', $meta),
            'breakdown' => '',
            'type_label' => 'Single',
            'qty' => $item->quantity.' pc(s)',
            'unit_price' => OrderMoney::decimal(OrderMoney::minor($item->unit_price, $precision), $precision).' '.$currency,
            'line_total' => OrderMoney::decimal(OrderMoney::minor($item->line_total, $precision), $precision).' '.$currency,
            'image_url' => $this->resolveItemImageUrl($item),
        ];
    }

    private function resolveItemImageUrl(?OrderItem $item): ?string
    {
        if (! $item) {
            return null;
        }

        $images = $item->productImages();
        if (! empty($images[0]['url'])) {
            return (string) $images[0]['url'];
        }

        $variant = $item->variant;
        $product = $variant?->product;
        $color = $variant?->color;

        $url = $variant?->media?->url
            ?? $color?->swatchMedia?->url
            ?? ($product && $variant?->color_id ? $product->gallery?->firstWhere('color_id', $variant->color_id)?->media?->url : null)
            ?? $product?->primaryMedia?->url
            ?? $product?->gallery?->first()?->media?->url
            ?? ($item->attributes['image_url'] ?? null);

        return $url ? (string) $url : null;
    }

    /**
     * @return array{width: int, height: int, data: string}|null
     */
    private function loadAndScaleImage(?string $url): ?array
    {
        if (! $url || ! extension_loaded('gd')) {
            return null;
        }

        $rawData = null;
        $parsedPath = parse_url($url, PHP_URL_PATH);
        if ($parsedPath) {
            $localPath = public_path(ltrim($parsedPath, '/'));
            if (file_exists($localPath)) {
                $rawData = @file_get_contents($localPath);
            }
        }

        if ($rawData === null && file_exists($url)) {
            $rawData = @file_get_contents($url);
        }

        if ($rawData === null && filter_var($url, FILTER_VALIDATE_URL)) {
            $ctx = stream_context_create([
                'http' => ['timeout' => 2],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $rawData = @file_get_contents($url, false, $ctx);
        }

        if (! $rawData) {
            return null;
        }

        $src = @imagecreatefromstring($rawData);
        if (! $src) {
            return null;
        }

        $targetSize = 100;
        $canvas = imagecreatetruecolor($targetSize, $targetSize);
        if (! $canvas) {
            imagedestroy($src);

            return null;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $targetSize, $targetSize, $white);

        $sw = imagesx($src);
        $sh = imagesy($src);
        if ($sw <= 0 || $sh <= 0) {
            imagedestroy($canvas);
            imagedestroy($src);

            return null;
        }

        $scale = min($targetSize / $sw, $targetSize / $sh);
        $dw = (int) round($sw * $scale);
        $dh = (int) round($sh * $scale);
        $dx = (int) round(($targetSize - $dw) / 2);
        $dy = (int) round(($targetSize - $dh) / 2);

        imagecopyresampled($canvas, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);

        ob_start();
        imagejpeg($canvas, null, 85);
        $jpegData = ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($src);

        if (! $jpegData) {
            return null;
        }

        return [
            'width' => $targetSize,
            'height' => $targetSize,
            'data' => $jpegData,
        ];
    }

    /**
     * @param  array<int, array{title: string, subtitle: string, breakdown: string, type_label: string, qty: string, unit_price: string, line_total: string, image_url: ?string}>  $rows
     * @param  array<string, string>  $imageUrlToName
     */
    private function renderPageStream(
        Order $order,
        string $storeName,
        array $rows,
        callable $fmt,
        bool $isFirstPage,
        bool $isLastPage,
        int $pageNum,
        int $totalPages,
        array $imageUrlToName = []
    ): string {
        $ops = [];

        // Top Navy Header Bar
        $ops[] = '0.07 0.09 0.15 rg 0 752 595 90 re f';
        $ops[] = $this->text(40, 805, $this->truncate($storeName, 36), 'F2', 18, [1, 1, 1]);
        $ops[] = $this->text(40, 785, 'COMMERCIAL INVOICE & PACKING SUMMARY', 'F1', 9, [0.75, 0.80, 0.88]);
        $ops[] = $this->text(40, 768, 'INVOICE '.$order->number, 'F2', 11, [0.95, 0.97, 1]);

        $dateStr = $order->created_at ? $order->created_at->format('Y-m-d H:i') : now()->format('Y-m-d');
        $statusStr = strtoupper(str_replace('_', ' ', (string) $order->status));
        $paymentStr = strtoupper(str_replace('_', ' ', (string) $order->payment_state));

        $ops[] = $this->text(380, 808, 'Date: '.$dateStr, 'F1', 9, [0.85, 0.88, 0.94]);
        $ops[] = $this->text(380, 793, 'Order Status: '.$statusStr, 'F2', 9, [1, 1, 1]);
        $ops[] = $this->text(380, 778, 'Payment: '.$paymentStr, 'F2', 9, [0.65, 0.92, 0.75]);
        $ops[] = $this->text(380, 764, 'Page '.$pageNum.' of '.$totalPages, 'F1', 8, [0.70, 0.75, 0.82]);

        $y = 732;

        if ($isFirstPage) {
            // Customer & Shipping Box (Left) + Tracking & Delivery Box (Right)
            $ops[] = '0.97 0.98 0.99 rg 0.86 0.88 0.91 RG 0.7 w 40 632 250 90 re B';
            $ops[] = '0.97 0.98 0.99 rg 0.86 0.88 0.91 RG 0.7 w 305 632 250 90 re B';

            $addr = is_array($order->shipping_address) ? $order->shipping_address : [];
            $cust = is_array($order->customer_snapshot) ? $order->customer_snapshot : [];
            $customerName = (string) ($addr['name'] ?? $cust['name'] ?? 'Customer');
            $customerPhone = (string) ($addr['phone'] ?? $cust['phone'] ?? '');
            $line1 = (string) ($addr['line1'] ?? '');
            $cityCountry = trim(implode(', ', array_filter([$addr['city'] ?? null, $addr['postal_code'] ?? null, $addr['country'] ?? null])));

            $ops[] = $this->text(50, 707, 'BILL & SHIP TO', 'F2', 8, [0.40, 0.45, 0.55]);
            $ops[] = $this->text(50, 691, $this->truncate($customerName, 38), 'F2', 10, [0.07, 0.09, 0.15]);
            if ($customerPhone !== '') {
                $ops[] = $this->text(50, 676, 'Phone: '.$customerPhone, 'F1', 9, [0.25, 0.30, 0.38]);
            }
            if ($line1 !== '') {
                $ops[] = $this->text(50, 662, $this->truncate($line1, 42), 'F1', 9, [0.25, 0.30, 0.38]);
            }
            if ($cityCountry !== '') {
                $ops[] = $this->text(50, 648, $this->truncate($cityCountry, 42), 'F1', 9, [0.25, 0.30, 0.38]);
            }

            $trackingCode = (string) ($order->tracking_code ?: $order->number);
            $carrierTracking = (string) ($order->tracking_number ?: 'Assigned upon dispatch');
            $deliveryMethod = (string) ($order->delivery_method ?: 'Standard Shipping');

            $ops[] = $this->text(315, 707, 'TRACKING & SHIPMENT DETAILS', 'F2', 8, [0.40, 0.45, 0.55]);
            $ops[] = $this->text(315, 691, 'Tracking Code: '.$this->truncate($trackingCode, 30), 'F2', 9, [0.07, 0.09, 0.15]);
            $ops[] = $this->text(315, 676, 'Carrier Tracking #: '.$this->truncate($carrierTracking, 28), 'F1', 9, [0.25, 0.30, 0.38]);
            $ops[] = $this->text(315, 662, 'Shipping Method: '.$this->truncate($deliveryMethod, 30), 'F1', 9, [0.25, 0.30, 0.38]);
            $ops[] = $this->text(315, 648, 'Track online at /track-order with tracking code', 'F1', 8, [0.35, 0.42, 0.55]);

            $y = 612;
        }

        // Table Header
        $ops[] = '0.12 0.16 0.23 rg 40 '.($y - 22).' 515 22 re f';
        $ops[] = $this->text(48, $y - 14, 'PRODUCT / WHOLESALE PACK DETAILS', 'F2', 8.5, [1, 1, 1]);
        $ops[] = $this->text(325, $y - 14, 'PACK / MODE', 'F2', 8.5, [1, 1, 1]);
        $ops[] = $this->text(395, $y - 14, 'QTY', 'F2', 8.5, [1, 1, 1]);
        $ops[] = $this->text(445, $y - 14, 'UNIT PRICE', 'F2', 8.5, [1, 1, 1]);
        $ops[] = $this->text(505, $y - 14, 'LINE TOTAL', 'F2', 8.5, [1, 1, 1]);

        $y -= 22;

        foreach ($rows as $idx => $row) {
            $hasBreakdown = $row['breakdown'] !== '';
            $rowHeight = $hasBreakdown ? 52 : 44;
            $nextY = $y - $rowHeight;

            if ($idx % 2 === 1) {
                $ops[] = '0.98 0.985 0.995 rg 40 '.$nextY.' 515 '.$rowHeight.' re f';
            }
            $ops[] = '0.88 0.90 0.93 RG 0.5 w 40 '.$nextY.' 515 '.$rowHeight.' re S';

            // Thumbnail image (36x36) centered vertically in the row
            $imgX = 48;
            $imgSize = 36;
            $imgY = $nextY + (int) round(($rowHeight - $imgSize) / 2);
            $imgUrl = $row['image_url'] ?? null;
            $imgName = $imgUrl ? ($imageUrlToName[$imgUrl] ?? null) : null;

            if ($imgName) {
                $ops[] = 'q '.$imgSize.' 0 0 '.$imgSize.' '.$imgX.' '.$imgY.' cm /'.$imgName.' Do Q';
                $ops[] = '0.85 0.88 0.92 RG 0.5 w '.$imgX.' '.$imgY.' '.$imgSize.' '.$imgSize.' re S';
            } else {
                $ops[] = '0.94 0.95 0.97 rg 0.82 0.85 0.90 RG 0.5 w '.$imgX.' '.$imgY.' '.$imgSize.' '.$imgSize.' re B';
                $lbl = $hasBreakdown ? 'PACK' : 'ITEM';
                $ops[] = $this->text($imgX + 7, $imgY + 14, $lbl, 'F2', 7, [0.55, 0.60, 0.70]);
            }

            // Product text starting at X = 92
            $textX = 92;
            if ($hasBreakdown) {
                $ops[] = $this->text($textX, $y - 14, $this->truncate($row['title'], 44), 'F2', 9.5, [0.07, 0.09, 0.15]);
                $ops[] = $this->text($textX, $y - 27, $this->truncate($row['subtitle'], 58), 'F1', 8, [0.32, 0.37, 0.46]);
                $ops[] = $this->text($textX, $y - 40, $this->truncate($row['breakdown'], 62), 'F2', 7.5, [0.15, 0.25, 0.45]);
            } else {
                $ops[] = $this->text($textX, $y - 16, $this->truncate($row['title'], 44), 'F2', 9.5, [0.07, 0.09, 0.15]);
                $ops[] = $this->text($textX, $y - 30, $this->truncate($row['subtitle'], 58), 'F1', 8, [0.32, 0.37, 0.46]);
            }

            // Columns 2-5 vertically centered
            $midY = $y - ($rowHeight / 2) + 2;
            $ops[] = $this->text(325, $midY, $this->truncate($row['type_label'], 13), 'F2', 8.5, [0.15, 0.20, 0.30]);
            $ops[] = $this->text(395, $midY, $this->truncate($row['qty'], 10), 'F2', 8.5, [0.07, 0.09, 0.15]);
            $ops[] = $this->text(445, $midY, $this->truncate($row['unit_price'], 14), 'F1', 8.5, [0.25, 0.30, 0.38]);
            $ops[] = $this->text(505, $midY, $this->truncate($row['line_total'], 14), 'F2', 9, [0.07, 0.09, 0.15]);

            $y = $nextY;
        }

        if ($isLastPage) {
            $boxTop = max(95, $y - 14);
            $boxBottom = $boxTop - 92;

            $ops[] = '0.97 0.98 0.99 rg 0.85 0.88 0.92 RG 0.7 w 335 '.$boxBottom.' 220 92 re B';
            $ops[] = $this->text(347, $boxTop - 16, 'Subtotal:', 'F1', 9, [0.30, 0.35, 0.43]);
            $ops[] = $this->text(455, $boxTop - 16, $fmt($order->subtotal), 'F2', 9, [0.07, 0.09, 0.15]);

            $ops[] = $this->text(347, $boxTop - 32, 'Discount:', 'F1', 9, [0.30, 0.35, 0.43]);
            $ops[] = $this->text(455, $boxTop - 32, '-'.$fmt($order->discount_amount), 'F1', 9, [0.10, 0.50, 0.30]);

            $ops[] = $this->text(347, $boxTop - 48, 'Shipping:', 'F1', 9, [0.30, 0.35, 0.43]);
            $ops[] = $this->text(455, $boxTop - 48, $fmt($order->shipping_amount), 'F1', 9, [0.07, 0.09, 0.15]);

            $ops[] = '0.07 0.09 0.15 rg 335 '.$boxBottom.' 220 28 re f';
            $ops[] = $this->text(347, $boxBottom + 10, 'TOTAL:', 'F2', 10, [1, 1, 1]);
            $ops[] = $this->text(445, $boxBottom + 10, $fmt($order->total), 'F2', 10.5, [1, 1, 1]);
        }

        // Footer
        $ops[] = '0.88 0.90 0.93 RG 0.5 w 40 48 515 0 re S';
        $ops[] = $this->text(
            40,
            34,
            'Thank you for your business. Track your order status at /track-order using Reference: '.($order->tracking_code ?: $order->number),
            'F1',
            8,
            [0.45, 0.50, 0.58]
        );

        return implode("\n", $ops);
    }

    /**
     * @param  array{0: float, 1: float, 2: float}  $rgb
     */
    private function text(float $x, float $y, string $text, string $font = 'F1', float $size = 10, array $rgb = [0, 0, 0]): string
    {
        $clean = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($clean === false || $clean === '') {
            $clean = preg_replace('/[^\x20-\x7E]/', '', $text) ?: '';
        }
        $escaped = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $clean);
        $color = sprintf('%.2F %.2F %.2F rg', $rgb[0], $rgb[1], $rgb[2]);

        return sprintf('BT %s /%s %.1F Tf %.1F %.1F Td (%s) Tj ET', $color, $font, $size, $x, $y, $escaped);
    }

    private function truncate(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max - 3).'...' : $value;
    }
}
