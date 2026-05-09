<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicTrackingController extends Controller
{
    public function __invoke(Request $request): View
    {
        $code = strtoupper(trim((string) $request->query('code', '')));
        $shipment = null;
        $trackingAvailable = Shipment::hasTrackingCodeColumn();

        if ($trackingAvailable && $code !== '') {
            $shipment = Shipment::with(['events.user', 'client'])
                ->where('tracking_code', $code)
                ->first();
        }

        return view('tracking.public', [
            'code' => $code,
            'shipment' => $shipment,
            'searched' => $code !== '',
            'trackingAvailable' => $trackingAvailable,
        ]);
    }
}
