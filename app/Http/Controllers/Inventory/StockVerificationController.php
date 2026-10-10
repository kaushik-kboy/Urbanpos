<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Api\V1\ObservationController as ApiObservationController;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\InventoryObservation;
use Illuminate\Http\Request;

/**
 * AdminLTE desktop screens for the Urban Pets Stock & Expiry Verification module:
 * Manager/Owner review queue + cross-staff History & Audit. Added 2026-10-10 per
 * explicit client instruction — the module must be reachable from the existing
 * Inventory menu, not only the Flutter app. See
 * URBANPETS_DATABASE_AND_WORKFLOW.md §8 item 19.
 *
 * Deliberately a thin wrapper: every authorization check, branch-scoping rule, and
 * the ledger-posting approval logic already live in ApiObservationController (the
 * Sanctum API controller) and are reused here via direct method calls rather than
 * reimplemented. A web-session request satisfies the exact same
 * `$request->user()->hasPermissionTo($perm, 'web')` / branch checks the API
 * controller performs (both guards resolve to the same `User` model + 'web'
 * permission rows), so there is exactly one place that can read or mutate an
 * observation — this controller never touches StockLedgerService or the
 * inventory_observations table directly.
 */
class StockVerificationController extends Controller
{
    public function __construct(private ApiObservationController $observations)
    {
    }

    /** Manager/Owner review queue — reuses ApiObservationController::pending(). */
    public function reviewQueue(Request $request)
    {
        $json = $this->observations->pending($request);
        $data = $json->getData(true);

        if ($json->getStatusCode() !== 200) {
            abort($json->getStatusCode(), $data['message'] ?? 'Forbidden');
        }

        return view('inventory.stock-verification.review-queue', [
            'observations' => $data,
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /** Cross-staff History & Audit — reuses ApiObservationController::index(). */
    public function history(Request $request)
    {
        $json = $this->observations->index($request);
        $data = $json->getData(true);

        if ($json->getStatusCode() !== 200) {
            abort($json->getStatusCode(), $data['message'] ?? 'Forbidden');
        }

        return view('inventory.stock-verification.history', [
            'observations' => $data,
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /** Observation detail — reuses ApiObservationController::show(). */
    public function show(Request $request, InventoryObservation $observation)
    {
        $json = $this->observations->show($request, $observation);
        $data = $json->getData(true);

        if ($json->getStatusCode() !== 200) {
            abort($json->getStatusCode(), $data['message'] ?? 'Forbidden');
        }

        return view('inventory.stock-verification.show', [
            'observation' => $data['data'],
        ]);
    }

    /** Approve — reuses ApiObservationController::approve() (same ledger logic). */
    public function approve(Request $request, InventoryObservation $observation)
    {
        $json = $this->observations->approve($request, $observation);

        if ($json->getStatusCode() >= 400) {
            return back()->withErrors(['approve' => $json->getData(true)['message'] ?? 'Approval failed.']);
        }

        return redirect()->route('inventory.stock-verification.review-queue')
            ->with('status', "Observation #{$observation->id} approved.");
    }

    /** Request recount — reuses ApiObservationController::requestRecount(). */
    public function requestRecount(Request $request, InventoryObservation $observation)
    {
        $json = $this->observations->requestRecount($request, $observation);

        if ($json->getStatusCode() >= 400) {
            return back()->withErrors(['recount' => $json->getData(true)['message'] ?? 'Request failed.']);
        }

        return redirect()->route('inventory.stock-verification.review-queue')
            ->with('status', "Recount requested for observation #{$observation->id}.");
    }

    /** Evidence photo — reuses ApiObservationController::photo() (same auth check). */
    public function photo(Request $request, InventoryObservation $observation)
    {
        return $this->observations->photo($request, $observation);
    }

    /** Reject — reuses ApiObservationController::reject(). */
    public function reject(Request $request, InventoryObservation $observation)
    {
        $json = $this->observations->reject($request, $observation);

        if ($json->getStatusCode() >= 400) {
            return back()->withErrors(['reject' => $json->getData(true)['message'] ?? 'Rejection failed.']);
        }

        return redirect()->route('inventory.stock-verification.review-queue')
            ->with('status', "Observation #{$observation->id} rejected.");
    }
}
