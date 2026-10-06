<!DOCTYPE html>
<html><head><meta charset="utf-8">@include('pdf._style')
<style>@page { margin: 14px; } body { font-size: 10px; } .big { font-size: 22px; font-weight: bold; letter-spacing: 1px; }</style></head>
<body>
    <table><tr><td style="border:0;padding:0"><span class="brand">{{ $brand['name'] }}</span><br><span class="muted">{{ __($shipment->service) }}</span></td>
        <td class="right" style="border:0;padding:0"><span class="big">{{ strtoupper($shipment->mode) }}</span></td></tr></table>
    <hr>
    <p class="muted" style="margin:6px 0 2px">{{ __('From') }}</p>
    <p style="margin:0"><strong>{{ $shipment->sender['name'] ?? '' }}</strong><br>{{ $shipment->origin['line1'] ?? '' }}<br>{{ $shipment->origin['postal_code'] ?? '' }} {{ $shipment->origin['city'] ?? '' }}, {{ $shipment->origin['country'] ?? '' }}<br>{{ $shipment->sender['phone'] ?? '' }}</p>
    <p class="muted" style="margin:10px 0 2px">{{ __('To') }}</p>
    <p style="margin:0;font-size:13px"><strong>{{ $shipment->recipient['name'] ?? '' }}</strong><br>{{ $shipment->destination['line1'] ?? '' }} {{ $shipment->destination['line2'] ?? '' }}<br>{{ $shipment->destination['postal_code'] ?? '' }} {{ $shipment->destination['city'] ?? '' }}<br><strong>{{ \App\Support\Geo::countryName($shipment->destination['country'] ?? '') }}</strong><br>{{ $shipment->recipient['phone'] ?? '' }}</p>
    <hr>
    <p style="text-align:center;margin:8px 0"><img src="{{ $barcode }}" style="height:60px;max-width:100%"><br><span class="big">{{ $shipment->tracking_number }}</span></p>
    <table><tr>
        <td style="border:0">{{ __('Weight') }}: {{ number_format($shipment->weight_g / 1000, 2) }} kg<br>{{ __('Packages') }}: {{ $shipment->packages->count() }}<br>{{ __('Order') }}: {{ $order?->number }}</td>
        <td class="right" style="border:0"><img src="{{ $qr }}" style="width:80px;height:80px"></td>
    </tr></table>
</body></html>
