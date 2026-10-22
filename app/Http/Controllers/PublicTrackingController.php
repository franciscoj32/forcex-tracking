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

        if ($code !== '') {
            $shipment = Shipment::with(['clientVisibleEvents', 'containers:id,shipment_id,numero', 'customerService:id,name,email'])
                ->where('tracking_code', $code)
                ->first();
        }

        return view('tracking.public', [
            'code' => $code,
            'shipment' => $shipment,
            'searched' => $code !== '',
        ]);
    }
}
