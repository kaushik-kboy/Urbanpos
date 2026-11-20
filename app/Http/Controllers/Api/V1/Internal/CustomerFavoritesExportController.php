<?php

namespace App\Http\Controllers\Api\V1\Internal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CustomerFavoritesExportController extends Controller
{
    /**
     * Export customer top favorite purchased products for Follow-Up CRM.
     */
    public function favorites(Request $request): JsonResponse
    {
        // 1. Internal Security Token & IP Check
        $expectedToken = config('services.followup_sync.token');
        $bearerToken = $request->bearerToken() ?? $request->header('X-Internal-Token');

        if (blank($expectedToken) || ! hash_equals((string) $expectedToken, (string) $bearerToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing internal sync token.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $allowedIps = config('services.followup_sync.allowed_ips', ['127.0.0.1', '::1', '*']);
        $clientIp = $request->ip();
        if (! in_array($clientIp, $allowedIps, true) && ! in_array('*', $allowedIps, true)) {
            return response()->json([
                'success' => false,
                'message' => "Access denied from IP address [{$clientIp}].",
            ], Response::HTTP_FORBIDDEN);
        }

        // 2. Extract and normalize phone parameter
        $rawPhone = (string) $request->query('phone', '');
        $digits = preg_replace('/\D+/', '', $rawPhone);
        $phone10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

        if (blank($phone10)) {
            return response()->json([
                'success' => false,
                'message' => 'Valid phone number is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $customerIds = Customer::query()
            ->where('mobile', $phone10)
            ->orWhere('mobile', 'like', "%{$phone10}")
            ->orWhere('phone', $phone10)
            ->orWhere('phone', 'like', "%{$phone10}")
            ->pluck('id')
            ->all();

        if (empty($customerIds)) {
            return response()->json([
                'success' => true,
                'items' => [],
                'customer_found' => false,
            ]);
        }

        $limit = min(max((int) $request->query('limit', 15), 1), 50);

        $topItems = DB::table('sales_bill_items')
            ->join('sales_bills', 'sales_bill_items.sales_bill_id', '=', 'sales_bills.id')
            ->join('items', 'sales_bill_items.item_id', '=', 'items.id')
            ->whereIn('sales_bills.customer_id', $customerIds)
            ->where('sales_bills.status', '!=', 'Cancelled')
            ->select([
                'items.id',
                'items.name',
                'items.item_code',
                'items.sell_price',
                'items.mrp',
                DB::raw('SUM(sales_bill_items.qty) as total_qty'),
                DB::raw('SUM(sales_bill_items.net_amount) as total_amount'),
                DB::raw('COUNT(DISTINCT sales_bills.id) as bills_count'),
                DB::raw('MAX(sales_bills.bill_date) as last_purchased_date'),
            ])
            ->groupBy(
                'items.id',
                'items.name',
                'items.item_code',
                'items.sell_price',
                'items.mrp'
            )
            ->orderByDesc('bills_count')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->get();

        $formatted = $topItems->map(function ($item) {
            return [
                'id'                  => $item->id,
                'name'                => $item->name,
                'item_code'           => $item->item_code,
                'sell_price'          => (float) $item->sell_price,
                'mrp'                 => (float) $item->mrp,
                'total_qty'           => (float) $item->total_qty,
                'total_amount'        => (float) ($item->total_amount ?? 0),
                'bills_count'         => (int) $item->bills_count,
                'last_purchased_date' => $item->last_purchased_date ? date('d M Y', strtotime($item->last_purchased_date)) : '',
            ];
        });

        return response()->json([
            'success'        => true,
            'customer_found' => true,
            'items'          => $formatted,
        ]);
    }
}
