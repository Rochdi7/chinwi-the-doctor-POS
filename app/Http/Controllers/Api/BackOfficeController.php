<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Caisse;
use App\Models\CaisseMouvement;
use App\Models\Setting;
use App\Services\DashboardService;
use App\Support\AuditDiff;
use App\Support\ScanQueue;
use App\Support\Barcode;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The read-mostly screens: dashboard, caisse journal, activity journal,
 * settings, and the phone scanner page's one action.
 */
class BackOfficeController extends Controller
{
    // ---- Tableau de bord --------------------------------------------------

    public function dashboard(Request $request): JsonResponse
    {
        $service = $this->dashboardFor($request);

        return response()->json([
            'stats' => $service->stats(),
            'mensuel' => $service->mensuel(),
            'statuts' => $service->statuts(),
            'top_clients' => $service->topClients(),
        ]);
    }

    public function dashboardDetail(Request $request, string $key): JsonResponse
    {
        abort_unless(in_array($key, ['ca', 'regle', 'impaye', 'caisse'], true), 404);

        return response()->json($this->dashboardFor($request)->detail($key));
    }

    private function dashboardFor(Request $request): DashboardService
    {
        $data = $request->validate([
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date', 'after_or_equal:du'],
        ]);

        return new DashboardService($data['du'] ?? null, $data['au'] ?? null);
    }

    // ---- Caisse (read only: it only moves with a cash payment) -------------

    public function caisse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['nullable', Rule::in(['entree', 'sortie'])],
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $page = CaisseMouvement::query()
            ->with('user:id,name')
            ->when($data['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($data['du'] ?? null, fn ($q, $d) => $q->whereDate('occurred_at', '>=', $d))
            ->when($data['au'] ?? null, fn ($q, $d) => $q->whereDate('occurred_at', '<=', $d))
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate($data['per_page'] ?? 50)
            ->through(fn (CaisseMouvement $m) => [
                'id' => $m->id,
                'occurred_at' => $m->occurred_at?->format('Y-m-d H:i:s.v'),
                'type' => $m->type,
                'montant' => (float) $m->montant,
                'solde_avant' => (float) $m->solde_avant,
                'solde_apres' => (float) $m->solde_apres,
                'motif' => $m->motif,
                'user' => $m->user?->name,
                'payment_id' => $m->payment_id,
            ]);

        return response()->json(['solde' => Caisse::solde()] + $page->toArray());
    }

    // ---- Journal (audit trail, millisecond timestamps) --------------------

    public function journal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:100'],
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $q = trim($data['q'] ?? '');

        $page = ActivityLog::query()
            ->when($data['event'] ?? null, fn ($query, $e) => $query->where('event', $e))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('description', 'like', "%{$q}%")
                ->orWhere('user_name', 'like', "%{$q}%")))
            ->when($data['du'] ?? null, fn ($query, $d) => $query->whereDate('occurred_at', '>=', $d))
            ->when($data['au'] ?? null, fn ($query, $d) => $query->whereDate('occurred_at', '<=', $d))
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate($data['per_page'] ?? 50)
            ->through(fn (ActivityLog $l) => [
                'id' => $l->id,
                'occurred_at' => $l->occurred_at?->format('Y-m-d H:i:s.v'),
                'user' => $l->user_name,
                'event' => $l->event,
                'subject_type' => $l->subject_type,
                'subject_id' => $l->subject_id,
                'description' => $l->description,
                'montant' => $l->montant === null ? null : (float) $l->montant,
                'ip' => $l->ip,
                // Readable before/after rows, as the Filament journal showed.
                'changes' => AuditDiff::rows($l),
                'remise' => AuditDiff::remise($l),
            ]);

        return response()->json($page);
    }

    public function journalEvents(): JsonResponse
    {
        return response()->json(['data' => ActivityLog::query()->distinct()->orderBy('event')->pluck('event')]);
    }

    // ---- Paramètres -----------------------------------------------------------

    private const SETTINGS = ['societe_nom', 'societe_adresse', 'societe_telephone', 'societe_email', 'societe_ice', 'societe_rc', 'devise', 'tva_defaut'];

    public function settings(): JsonResponse
    {
        return response()->json(collect(self::SETTINGS)->mapWithKeys(fn ($k) => [$k => Setting::get($k)]));
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'societe_nom' => ['required', 'string', 'max:255'],
            'societe_adresse' => ['nullable', 'string', 'max:1000'],
            'societe_telephone' => ['nullable', 'string', 'max:60'],
            'societe_email' => ['nullable', 'email', 'max:255'],
            'societe_ice' => ['nullable', 'string', 'max:60'],
            'societe_rc' => ['nullable', 'string', 'max:60'],
            'devise' => ['nullable', 'string', 'max:10'],
            'tva_defaut' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        foreach (self::SETTINGS as $key) {
            Setting::put($key, $data[$key] ?? null);
        }

        ActivityLog::record('settings.updated', null, 'Paramètres modifiés');

        return $this->settings();
    }

    // ---- Phone used as the scanner (was ScannerTelephone::envoyer) ------------

    /** The till code this user's POS listens on. */
    public function scannerTill(Request $request): JsonResponse
    {
        return response()->json(['till' => ScanQueue::tillFor($request->user()->id)]);
    }

    /** A code read by the phone camera: named back, and queued for the till. */
    public function scannerEnvoyer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'till' => ['nullable', 'string'],
        ]);

        $till = ScanQueue::isValid((string) ($data['till'] ?? ''))
            ? $data['till']
            : ScanQueue::tillFor($request->user()->id);

        $code = Barcode::normalizeScan($data['code']);

        if ($code === '') {
            return response()->json(['ok' => false, 'message' => __('app.scanner.rien')]);
        }

        $article = Article::findByScan($code);

        if (! $article) {
            return response()->json(['ok' => false, 'message' => __('app.scan.introuvable', ['code' => $code])]);
        }

        ScanQueue::push($till, $code);

        return response()->json(['ok' => true, 'message' => $article->designation]);
    }
}
