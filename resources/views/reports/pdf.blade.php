<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>{{ $report['title'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #202124; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .meta { color: #666; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f3f4; text-align: left; font-size: 9px; text-transform: uppercase; }
        th, td { border: 1px solid #dadce0; padding: 5px 6px; }
        td.num { text-align: right; }
    </style>
</head>

<body>
    <h1>{{ $report['title'] }}</h1>
    <div class="meta">
        {{ $outlet->name }} · {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }} · dicetak {{ now()->format('d M Y H:i') }}
    </div>

    <table>
        <thead>
            <tr>@foreach($report['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse($report['rows'] as $row)
                <tr>
                    @foreach($row as $cell)
                        <td class="{{ is_numeric($cell) ? 'num' : '' }}">{{ is_numeric($cell) ? rtrim(rtrim(number_format($cell, 4, ',', '.'), '0'), ',') : $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($report['columns']) }}">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>

</html>
