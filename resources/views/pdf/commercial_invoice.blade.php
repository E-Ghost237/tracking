<!DOCTYPE html>
<html><head><meta charset="utf-8">@include('pdf._style')</head>
<body>
    <h1>{{ __('Commercial invoice') }}</h1>
    <p class="muted">{{ $shipment->tracking_number }} · {{ now()->translatedFormat('j F Y') }}</p>
    <table><tr>
        <td class="box" style="width:50%"><span class="muted">{{ __('Exporter / sender') }}</span><br><strong>{{ $shipment->sender['name'] ?? '' }}</strong><br>{{ $shipment->origin['line1'] ?? '' }}<br>{{ $shipment->origin['city'] ?? '' }}, {{ \App\Support\Geo::countryName($shipment->origin['country'] ?? '') }}<br>{{ $shipment->sender['phone'] ?? '' }}</td>
        <td class="box" style="width:50%"><span class="muted">{{ __('Consignee / recipient') }}</span><br><strong>{{ $shipment->recipient['name'] ?? '' }}</strong><br>{{ $shipment->destination['line1'] ?? '' }}<br>{{ $shipment->destination['city'] ?? '' }}, {{ \App\Support\Geo::countryName($shipment->destination['country'] ?? '') }}<br>{{ $shipment->recipient['phone'] ?? '' }}</td>
    </tr></table>
    <br>
    <table>
        <thead><tr><th>{{ __('Description') }}</th><th>{{ __('Category') }}</th><th class="right">{{ __('Weight') }}</th><th class="right">{{ __('Value') }}</th></tr></thead>
        <tbody>
            @foreach ($shipment->packages as $package)
                <tr><td>{{ $package->description }}</td><td>{{ __('category.'.$package->category) }}</td><td class="right">{{ number_format($package->weight_g / 1000, 2) }} kg</td><td class="right">{{ \App\Support\Money::format($package->declared_value, 'USD') }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <p>{{ __('Contents') }}: {{ $shipment->customs['contents'] ?? '—' }} · {{ __('HS code') }}: {{ $shipment->customs['hs_code'] ?? '—' }} · {{ __('Reason for export') }}: {{ $shipment->customs['reason'] ?? '—' }}</p>
    <p class="muted">{{ __('I declare that the information in this invoice is true and correct and that the contents of this shipment are as stated above.') }}</p>
    <br><p>{{ __('Signature') }}: ______________________</p>
</body></html>
