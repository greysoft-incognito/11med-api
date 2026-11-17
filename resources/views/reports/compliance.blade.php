<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>11med compliance report</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0A1628; }
    h1 { font-size: 18px; margin: 0 0 4px; }
    h2 { font-size: 13px; margin: 18px 0 6px; }
    .muted { color: #5A6B7A; }
    table { width: 100%; border-collapse: collapse; }
    th, td { text-align: left; padding: 4px 6px; border-bottom: 1px solid #E4EFED; }
    th { color: #5A6B7A; font-weight: normal; }
    .score { font-size: 22px; font-weight: bold; }
</style>
</head>
<body>
    <h1>Compliance report</h1>
    <div class="muted">{{ $patient->full_name }} &middot; {{ $from }} to {{ $to }} ({{ $compliance['window_days'] }} days) &middot; generated {{ $generated }} (Lagos time)</div>

    <h2>Compliance</h2>
    <div class="score">{{ $compliance['score'] }}%</div>
    <div>{{ $compliance['doses_taken'] }} of {{ $compliance['doses_scheduled'] }} scheduled doses taken.</div>
    <div class="muted">Compliance is doses taken, on time or late, divided by doses scheduled, times 100. Skipped and missed doses count as not taken. Paused days have no doses.</div>

    <h2>Skipped doses by reason</h2>
    @if (count($skips) === 0)
        <div>No doses were skipped.</div>
    @else
        <table>
            @foreach ($skips as $reason => $count)
                <tr><td>{{ str_replace('_', ' ', ucfirst($reason)) }}</td><td>{{ $count }}</td></tr>
            @endforeach
        </table>
    @endif

    <h2>Current medications</h2>
    @if ($medications->isEmpty())
        <div>None.</div>
    @else
        <table>
            <tr><th>Medicine</th><th>Strength</th><th>Dose</th><th>Times</th></tr>
            @foreach ($medications as $medication)
                <tr>
                    <td>{{ $medication->drug_name }}@if ($medication->brand_name) ({{ $medication->brand_name }})@endif</td>
                    <td>{{ $medication->dosage_strength }}</td>
                    <td>{{ rtrim(rtrim(number_format($medication->dose_quantity, 2), '0'), '.') }} {{ $medication->dose_unit?->value === 'spoon_5ml' ? '5 ml spoon' : ($medication->dose_unit?->value ?? $medication->dosage_form->value) }}</td>
                    <td>{{ implode(', ', $medication->specific_times) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>By day</h2>
    <table>
        <tr><th>Date</th><th>Taken</th><th>Scheduled</th><th>Score</th></tr>
        @foreach (array_reverse($compliance['daily']) as $day)
            <tr>
                <td>{{ $day['date'] }}</td>
                <td>{{ $day['taken'] }}</td>
                <td>{{ $day['scheduled'] }}</td>
                <td>{{ $day['score'] === null ? 'Nothing scheduled' : $day['score'] . '%' }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
