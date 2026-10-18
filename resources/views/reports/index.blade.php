@extends('layouts.app')

@section('title', 'Laporan')

@section('content')
@php
    $query = ['type' => $type, 'from' => $from->toDateString(), 'to' => $to->toDateString()];
    $usesPeriod = in_array($type, ['movements', 'usage', 'opname'], true);
@endphp

<x-page-header title="Laporan" :subtitle="$report['title'] . ($usesPeriod ? ' · ' . $from->format('d M Y') . ' – ' . $to->format('d M Y') : '')">
    <x-slot:actions>
        <a href="{{ route('reports.export', $query + ['format' => 'csv']) }}" class="btn btn-outline btn-sm min-h-11 gap-2"><i data-lucide="file-spreadsheet" class="w-4 h-4"></i> CSV</a>
        <a href="{{ route('reports.export', $query + ['format' => 'pdf']) }}" class="btn btn-outline btn-sm min-h-11 gap-2"><i data-lucide="file-text" class="w-4 h-4"></i> PDF</a>
    </x-slot:actions>
</x-page-header>

<form method="GET" class="bg-base-100 border border-base-300 rounded-2xl p-4 grid sm:grid-cols-4 gap-3 items-end">
    <x-field label="Jenis laporan" name="type">
        <select name="type" class="select w-full min-h-11 text-sm rounded-xl">
            @foreach($types as $key => $label)
                <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </x-field>
    <x-field label="Dari" name="from"><input type="date" name="from" value="{{ $from->toDateString() }}" class="input w-full min-h-11 text-sm rounded-xl" /></x-field>
    <x-field label="Sampai" name="to"><input type="date" name="to" value="{{ $to->toDateString() }}" class="input w-full min-h-11 text-sm rounded-xl" /></x-field>
    <button class="btn btn-neutral min-h-11">Tampilkan</button>
</form>

<div class="bg-base-100 border border-base-300 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table">
            <thead class="bg-base-200/20 text-[10px] uppercase tracking-wider font-extrabold text-base-content/50">
                <tr>@foreach($report['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr>
            </thead>
            <tbody class="[&>tr:hover]:bg-base-200/30">
                @forelse($report['rows'] as $row)
                    <tr>
                        @foreach($row as $cell)
                            <td class="{{ is_numeric($cell) ? 'text-right' : '' }}">
                                {{ is_numeric($cell) ? rtrim(rtrim(number_format($cell, 4, ',', '.'), '0'), ',') : $cell }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($report['columns']) }}" class="text-center py-12 text-xs text-base-content/50">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
