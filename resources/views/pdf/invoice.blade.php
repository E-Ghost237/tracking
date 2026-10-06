<!DOCTYPE html>
<html><head><meta charset="utf-8">@include('pdf._style')</head>
<body>
    <table><tr>
        <td style="border:0"><span class="brand">{{ $brand['name'] }}</span><br><span class="muted">{{ $brand['legal_name'] }}<br>{{ $brand['address'] }}<br>{{ $brand['support_email'] }}</span></td>
        <td class="right" style="border:0"><h1>{{ __('Invoice') }}</h1><span class="muted">{{ $invoice?->number }}<br>{{ $invoice?->issued_at?->translatedFormat('j F Y') }}</span></td>
    </tr></table>
    <br>
    <table><tr>
        <td class="box" style="width:50%"><span class="muted">{{ __('Billed to') }}</span><br><strong>{{ $order?->user?->name }}</strong><br>{{ $order?->user?->email }}</td>
        <td class="box" style="width:50%"><span class="muted">{{ __('Shipment') }}</span><br><strong>{{ $shipment->tracking_number }}</strong><br>{{ $shipment->originLabel() }} → {{ $shipment->destinationLabel() }}</td>
    </tr></table>
    <br>
    <table>
        <thead><tr><th>{{ __('Description') }}</th><th class="right">{{ __('Amount') }}</th></tr></thead>
        <tbody>
            @php($breakdown = $order?->quote?->price_breakdown['breakdown'] ?? [])
            <tr><td>{{ __($shipment->service) }} · {{ number_format($shipment->chargeable_weight_g / 1000, 2) }} kg {{ __('chargeable') }}</td><td class="right">{{ \App\Support\Money::format($breakdown['freight'] ?? $order->subtotal, $order->currency) }}</td></tr>
            @foreach ($breakdown['surcharges'] ?? [] as $surcharge)
                <tr><td>{{ __($surcharge['name']) }}</td><td class="right">{{ \App\Support\Money::format($surcharge['amount'], $order->currency) }}</td></tr>
            @endforeach
            @if ($order->fee > 0)
                <tr><td>{{ __('Payment method fee') }}</td><td class="right">{{ \App\Support\Money::format($order->fee, $order->currency) }}</td></tr>
            @endif
            <tr><td class="total">{{ __('Total') }}</td><td class="right total">{{ \App\Support\Money::format($order->total, $order->currency) }}</td></tr>
        </tbody>
    </table>
    <p class="muted">{{ __('Paid on :date · Receipt :receipt · Reference :reference', ['date' => $order->paid_at?->translatedFormat('j F Y'), 'receipt' => $order->receipt_number, 'reference' => $order->payment_reference]) }}</p>
</body></html>
