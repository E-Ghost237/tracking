<!DOCTYPE html>
<html><head><meta charset="utf-8">@include('pdf._style')</head>
<body>
    <span class="brand">{{ $brand['name'] }}</span>
    <h1 style="margin-top:14px">{{ __('Payment receipt') }}</h1>
    <p class="muted">{{ $order?->receipt_number }} · {{ $order?->paid_at?->translatedFormat('j F Y, H:i') }} UTC</p>
    <table>
        <tr><td>{{ __('Order') }}</td><td class="right">{{ $order?->number }}</td></tr>
        <tr><td>{{ __('Payment reference') }}</td><td class="right">{{ $order?->payment_reference }}</td></tr>
        <tr><td>{{ __('Tracking number') }}</td><td class="right">{{ $shipment->tracking_number }}</td></tr>
        <tr><td>{{ __('Customer') }}</td><td class="right">{{ $order?->user?->name }}</td></tr>
        <tr><td class="total">{{ __('Amount received') }}</td><td class="right total">{{ \App\Support\Money::format($order->amount_paid, $order->currency) }}</td></tr>
    </table>
    <p class="muted">{{ __('Payment verified by our team. Keep this receipt for your records.') }}</p>
</body></html>
