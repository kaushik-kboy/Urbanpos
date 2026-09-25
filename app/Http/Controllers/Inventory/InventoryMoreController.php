<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryMoreController extends Controller
{
    public function __construct(private StockLedgerService $stockLedger)
    {
    }

    public function repack(Request $request)
    {
        $branchId = $request->input('branch_id', session('active_branch_id') ?? Branch::where('name', '!=', 'GLOBAL')->first()->id ?? 2);
        $branches = Branch::where('status', true)->pluck('name', 'id');
        // Items are searched via AJAX (item-list endpoint) — do NOT load all items here.
        return view('inventory.more.repack', compact('branches', 'branchId'));
    }

    public function processRepack(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'bulk_item_id' => ['required', 'exists:items,id'],
            'bulk_qty_taken' => ['required', 'numeric', 'min:0.01'],
            'conversion_loss' => ['nullable', 'numeric', 'min:0'],
            'packs' => ['required', 'array', 'min:1'],
            'packs.*.item_id' => ['required', 'exists:items,id'],
            'packs.*.qty' => ['required', 'numeric', 'min:0.01'],
        ]);

        $branchId = (int) $data['branch_id'];
        $bulkItemId = (int) $data['bulk_item_id'];
        $bulkQty = (float) $data['bulk_qty_taken'];

        DB::transaction(function () use ($branchId, $bulkItemId, $bulkQty, $data) {
            // Deduct bulk item at its current average cost.
            $bulkLedger = $this->stockLedger->post(
                itemId: $bulkItemId,
                branchId: $branchId,
                movementType: 'REPACK',
                qtyDelta: -$bulkQty,
                unitCost: null,
                referenceType: null,
                referenceId: null,
                documentDate: now()->toDateString(),
                reasonCode: 'REPACK_BULK_BREAK',
            );

            // Value released from the bulk item is redistributed uniformly per unit across
            // the resulting packs — a repack must not create or destroy inventory value,
            // so a never-before-stocked pack size cannot be valued at its own (zero) average.
            $totalValueOut = $bulkQty * (float) $bulkLedger->unit_cost;
            $totalPackQty = collect($data['packs'])->sum('qty');
            $perUnitPackCost = $totalPackQty > 0 ? $totalValueOut / $totalPackQty : 0.0;

            foreach ($data['packs'] as $pack) {
                $this->stockLedger->post(
                    itemId: (int) $pack['item_id'],
                    branchId: $branchId,
                    movementType: 'REPACK',
                    qtyDelta: (float) $pack['qty'],
                    unitCost: $perUnitPackCost,
                    referenceType: null,
                    referenceId: null,
                    documentDate: now()->toDateString(),
                    reasonCode: 'REPACK_PACK_CREATE',
                );
            }
        });

        return back()->with('status', 'Repack operation completed and stock adjusted successfully.');
    }

    public function kitPreparation(Request $request)
    {
        $branchId = $request->input('branch_id', session('active_branch_id') ?? Branch::where('name', '!=', 'GLOBAL')->first()->id ?? 2);
        $branches = Branch::where('status', true)->pluck('name', 'id');
        // Items are searched via AJAX — do NOT load all items here.
        return view('inventory.more.kit-preparation', compact('branches', 'branchId'));
    }

    public function processKitPreparation(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'kit_item_id' => ['required', 'exists:items,id'],
            'kit_qty' => ['required', 'numeric', 'min:1'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.item_id' => ['required', 'exists:items,id'],
            'components.*.qty_per_kit' => ['required', 'numeric', 'min:0.01'],
        ]);

        $branchId = (int) $data['branch_id'];
        $kitItemId = (int) $data['kit_item_id'];
        $kitQty = (float) $data['kit_qty'];

        DB::transaction(function () use ($branchId, $kitItemId, $kitQty, $data) {
            // Deduct each component at its own current average cost, accumulating the
            // total value consumed so the assembled kit is valued from what actually went
            // into it, not left at whatever (possibly zero) average the kit item already had.
            $totalComponentValue = 0.0;
            foreach ($data['components'] as $comp) {
                $compTotalQty = (float) $comp['qty_per_kit'] * $kitQty;
                $compLedger = $this->stockLedger->post(
                    itemId: (int) $comp['item_id'],
                    branchId: $branchId,
                    movementType: 'KIT_ASSEMBLY',
                    qtyDelta: -$compTotalQty,
                    unitCost: null,
                    referenceType: null,
                    referenceId: null,
                    documentDate: now()->toDateString(),
                    reasonCode: 'KIT_ASSEMBLY_COMPONENT',
                );
                $totalComponentValue += $compTotalQty * (float) $compLedger->unit_cost;
            }

            $kitUnitCost = $kitQty > 0 ? $totalComponentValue / $kitQty : 0.0;
            $this->stockLedger->post(
                itemId: $kitItemId,
                branchId: $branchId,
                movementType: 'KIT_ASSEMBLY',
                qtyDelta: $kitQty,
                unitCost: $kitUnitCost,
                referenceType: null,
                referenceId: null,
                documentDate: now()->toDateString(),
                reasonCode: 'KIT_ASSEMBLY_OUTPUT',
            );
        });

        return back()->with('status', "Kit Preparation completed: {$kitQty} kit units created.");
    }

    public function kitUnpack(Request $request)
    {
        $branchId = $request->input('branch_id', session('active_branch_id') ?? Branch::where('name', '!=', 'GLOBAL')->first()->id ?? 2);
        $branches = Branch::where('status', true)->pluck('name', 'id');
        // Items are searched via AJAX — do NOT load all items here.
        return view('inventory.more.kit-unpack', compact('branches', 'branchId'));
    }

    public function processKitUnpack(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'kit_item_id' => ['required', 'exists:items,id'],
            'unpack_qty' => ['required', 'numeric', 'min:1'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.item_id' => ['required', 'exists:items,id'],
            'components.*.qty_per_kit' => ['required', 'numeric', 'min:0.01'],
        ]);

        $branchId = (int) $data['branch_id'];
        $kitItemId = (int) $data['kit_item_id'];
        $unpackQty = (float) $data['unpack_qty'];

        DB::transaction(function () use ($branchId, $kitItemId, $unpackQty, $data) {
            $kitLedger = $this->stockLedger->post(
                itemId: $kitItemId,
                branchId: $branchId,
                movementType: 'KIT_DISASSEMBLY',
                qtyDelta: -$unpackQty,
                unitCost: null,
                referenceType: null,
                referenceId: null,
                documentDate: now()->toDateString(),
                reasonCode: 'KIT_DISASSEMBLY_INPUT',
            );

            // Same value-conservation rule as repack: the kit's released value is
            // redistributed uniformly per unit across the returning components.
            $releasedValue = $unpackQty * (float) $kitLedger->unit_cost;
            $totalCompQty = collect($data['components'])->sum(fn ($c) => (float) $c['qty_per_kit'] * $unpackQty);
            $perUnitCompCost = $totalCompQty > 0 ? $releasedValue / $totalCompQty : 0.0;

            foreach ($data['components'] as $comp) {
                $compTotalQty = (float) $comp['qty_per_kit'] * $unpackQty;
                $this->stockLedger->post(
                    itemId: (int) $comp['item_id'],
                    branchId: $branchId,
                    movementType: 'KIT_DISASSEMBLY',
                    qtyDelta: $compTotalQty,
                    unitCost: $perUnitCompCost,
                    referenceType: null,
                    referenceId: null,
                    documentDate: now()->toDateString(),
                    reasonCode: 'KIT_DISASSEMBLY_COMPONENT',
                );
            }
        });

        return back()->with('status', "Kit Unpacked successfully: {$unpackQty} kit units returned to components.");
    }

    public function priceDrop(Request $request)
    {
        $branchId = $request->input('branch_id', session('active_branch_id') ?? Branch::where('name', '!=', 'GLOBAL')->first()->id ?? 2);
        $branches = Branch::where('status', true)->pluck('name', 'id');
        $invoices = PurchaseInvoice::with('supplier')->where('branch_id', $branchId)->latest()->limit(25)->get();

        return view('inventory.more.price-drop', compact('branches', 'branchId', 'invoices'));
    }

    public function shelfTalker(Request $request)
    {
        $branchId = $request->input('branch_id', session('active_branch_id') ?? Branch::where('name', '!=', 'GLOBAL')->first()->id ?? 2);
        $branches = Branch::where('status', true)->pluck('name', 'id');
        $items = Item::where('status', true)->orderBy('name')->limit(30)->get();

        return view('inventory.more.shelf-talker', compact('branches', 'branchId', 'items'));
    }

    public function changeSerialNo(Request $request)
    {
        $branchId = $request->input('branch_id', session('active_branch_id') ?? Branch::where('name', '!=', 'GLOBAL')->first()->id ?? 2);
        $branches = Branch::where('status', true)->pluck('name', 'id');
        // Items searched via AJAX; serialized filter applied server-side in item-search endpoint.
        return view('inventory.more.change-serial-no', compact('branches', 'branchId'));
    }

    public function processChangeSerialNo(Request $request)
    {
        $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'serials' => ['required', 'array', 'min:1'],
            'serials.*.old_serial' => ['nullable', 'string'],
            'serials.*.new_serial' => ['required', 'string'],
        ]);

        return back()->with('status', 'Serial numbers updated successfully.');
    }
}
