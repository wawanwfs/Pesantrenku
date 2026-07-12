<?php

namespace App\Http\Controllers;

use App\Services\MetricRegistry;
use Illuminate\Http\Request;

class MetricsController extends Controller
{
    public function __invoke(Request $request, MetricRegistry $metrics)
    {
        $expected = (string) config('pesantrenku.observability.metrics_token');
        abort_if(app()->environment('production') && $expected === '', 503, 'Metrics token belum dikonfigurasi.');
        abort_if($expected !== '' && ! hash_equals($expected, (string) $request->bearerToken()), 403);

        return response($metrics->exposition(), 200, ['Content-Type' => 'text/plain; version=0.0.4; charset=utf-8']);
    }
}
