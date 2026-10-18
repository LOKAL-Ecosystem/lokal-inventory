<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports): View
    {
        [$type, $from, $to] = $this->filters($request);

        return view('reports.index', [
            'types' => ReportService::TYPES,
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'report' => $reports->build($type, $from, $to),
        ]);
    }

    public function export(Request $request, ReportService $reports): StreamedResponse|Response
    {
        [$type, $from, $to] = $this->filters($request);
        $report = $reports->build($type, $from, $to);
        $filename = 'laporan-' . Str::slug($report['title']) . '-' . $from->toDateString() . '_' . $to->toDateString();

        if ($request->query('format') === 'pdf') {
            return Pdf::loadView('reports.pdf', [
                'report' => $report,
                'from' => $from,
                'to' => $to,
                'outlet' => $request->user()->outlet,
            ])->setPaper('a4', count($report['columns']) > 5 ? 'landscape' : 'portrait')
                ->download("{$filename}.pdf");
        }

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, $report['columns']);
            foreach ($report['rows'] as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, "{$filename}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: string, 1: Carbon, 2: Carbon}
     */
    protected function filters(Request $request): array
    {
        $data = $request->validate([
            'type' => ['nullable', 'in:' . implode(',', array_keys(ReportService::TYPES))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = Carbon::parse($data['from'] ?? now()->startOfMonth());
        $to = Carbon::parse($data['to'] ?? now());

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return [$data['type'] ?? 'movements', $from, $to];
    }
}
