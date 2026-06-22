<?php

namespace App\Services;

use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class UpiQrService
{
    public function paymentUri(string $upiId, ?string $payeeName = null): string
    {
        $params = [
            'pa' => trim($upiId),
            'cu' => 'INR',
        ];

        if (filled($payeeName)) {
            $params['pn'] = trim($payeeName);
        }

        return 'upi://pay?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function svgMarkup(string $upiId, ?string $payeeName = null, int $size = 220): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'scale' => max(4, (int) floor($size / 40)),
            'addQuietzone' => true,
            'quietzoneSize' => 2,
        ]);

        return (new QRCode($options))->render($this->paymentUri($upiId, $payeeName));
    }

    public function previewHtml(string $upiId, ?string $payeeName = null): string
    {
        $paymentUri = e($this->paymentUri($upiId, $payeeName));
        $qrImage = e($this->svgMarkup($upiId, $payeeName));

        return <<<HTML
<div class="flex flex-col items-start gap-3">
    <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
        <img src="{$qrImage}" alt="UPI QR code" class="h-44 w-44" />
    </div>
    <p class="text-xs text-gray-500 dark:text-gray-400 break-all">Scan opens: <span class="font-mono">{$paymentUri}</span></p>
</div>
HTML;
    }
}
