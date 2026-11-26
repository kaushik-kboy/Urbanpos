<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = PurchaseOrder::with(['supplier:id,name,phone,mobile', 'branch:id,name'])
            ->withCount('items');

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->query('supplier_id'));
        }

        if ($request->filled('status')) {
            $status = $request->query('status');
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        } else {
            $query->where('status', 'Open');
        }

        if ($user && $user->branch_id && ! $user->hasRole('Owner') && $user->email !== 'admin@urbanpos.com') {
            $query->where('branch_id', $user->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $orders = $query->orderByDesc('po_date')->limit(50)->get()->map(function ($po) {
            return [
                'id' => (string) $po->id,
                'po_number' => $po->po_number,
                'po_date' => $po->po_date ? $po->po_date->format('Y-m-d') : null,
                'supplier_id' => (string) $po->supplier_id,
                'supplier_name' => $po->supplier?->name ?? 'Unknown Supplier',
                'supplier_phone' => $po->supplier?->mobile ?? $po->supplier?->phone,
                'branch_id' => (string) $po->branch_id,
                'branch_name' => $po->branch?->name ?? 'Main Branch',
                'status' => $po->status,
                'total_qty' => (float) $po->total_qty,
                'total_items' => $po->items_count,
                'total_amount' => (float) $po->total,
                'remarks' => $po->remarks,
            ];
        });

        return response()->json(['data' => $orders]);
    }

    public function show(Request $request, int|string $id): JsonResponse
    {
        $po = PurchaseOrder::with([
            'supplier:id,name,phone,mobile',
            'branch:id,name',
            'items.item:id,name,item_code,ean_upc_code,mrp,cost_price,sell_price',
        ])->find($id);

        if (! $po) {
            return response()->json(['message' => 'Purchase order not found.'], 404);
        }

        $items = $po->items->map(function ($poItem) {
            $item = $poItem->item;

            return [
                'id' => (string) $poItem->id,
                'po_item_id' => (string) $poItem->id,
                'item_id' => (string) $poItem->item_id,
                'name' => $item?->name ?? 'Unknown Item',
                'item_code' => $item?->item_code,
                'barcode' => $item?->ean_upc_code ?? $item?->item_code ?? '',
                'ordered_qty' => (float) $poItem->qty,
                'received_qty' => (float) $poItem->received_qty,
                'cost_price' => (float) $poItem->cost_price,
                'mrp' => (float) $poItem->mrp,
            ];
        });

        return response()->json([
            'data' => [
                'id' => (string) $po->id,
                'po_number' => $po->po_number,
                'po_date' => $po->po_date ? $po->po_date->format('Y-m-d') : null,
                'supplier_id' => (string) $po->supplier_id,
                'supplier_name' => $po->supplier?->name ?? 'Unknown Supplier',
                'branch_id' => (string) $po->branch_id,
                'branch_name' => $po->branch?->name ?? 'Main Branch',
                'status' => $po->status,
                'total_qty' => (float) $po->total_qty,
                'total_items' => $po->items->count(),
                'total_amount' => (float) $po->total,
                'remarks' => $po->remarks,
                'items' => $items,
            ],
        ]);
    }
}
