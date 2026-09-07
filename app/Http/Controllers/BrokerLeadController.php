<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\Property;
use App\Services\CustomFieldService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Enquiries logged by a broker from their portal.
 *
 * A broker records the client in front of them — who they are, which property
 * they were shown, how it went — and it lands in the CRM's Leads as a normal
 * lead, attributed back to them. They see their own enquiries here; everything
 * else about lead management stays in the CRM.
 */
class BrokerLeadController extends Controller
{
    /** Largest client photo accepted, in kilobytes. */
    public const MAX_PHOTO_KILOBYTES = 4096;

    /**
     * The enquiries this broker has logged.
     */
    public function index(Request $request)
    {
        $this->ensureEnabled($request);

        $mine = fn () => Lead::where('broker_id', $request->user()->id);

        $query = $mine()->with(['visitedProperty', 'clientPhoto']);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return view('broker.leads.index', [
            'leads' => $query->latest()->paginate(20)->withQueryString(),
            'statuses' => CustomFieldService::getOptions('lead_status'),
            // A broker's own tally of the work they brought in. Counted from
            // their own rows only, the same restriction as the list itself.
            'stats' => [
                'total' => $mine()->count(),
                'this_month' => $mine()->whereBetween('created_at', [
                    now()->startOfMonth(), now()->endOfMonth(),
                ])->count(),
                'this_week' => $mine()->where('created_at', '>=', now()->startOfWeek())->count(),
            ],
        ]);
    }

    /**
     * Move one of this broker's own enquiries along.
     *
     * A broker learns how a client is progressing days after the visit, and
     * until now had no way to say so — the enquiry sat at whatever status it was
     * logged with. They may change the status and append to the notes on their
     * own enquiries, and nothing else about the lead.
     */
    public function update(Request $request, Lead $lead)
    {
        abort_unless($lead->broker_id === $request->user()->id, 404);

        $validated = $request->validate([
            'status' => 'required|string|in:'.implode(',', CustomFieldService::getValidSlugs('lead_status')),
            'note' => 'nullable|string|max:2000',
        ]);

        $previousStatus = $lead->status;

        $attributes = ['status' => $validated['status']];

        if (filled($validated['note'] ?? null)) {
            // Appended, never replaced: the note is the history of the client
            // relationship and the CRM side reads it too.
            $stamp = now()->format('d M Y, g:i A');
            $attributes['notes'] = trim(
                ($lead->notes ? $lead->notes."\n\n" : '')
                ."[{$stamp}] ".$validated['note']
            );
        }

        $lead->update($attributes);

        if ($previousStatus !== $lead->status) {
            AuditLog::log('lead.status_changed', $lead);

            // The same hook the CRM fires, so a status change made from the
            // portal triggers the client's WhatsApp message like any other.
            event(new \App\Events\LeadStatusChanged($lead, $previousStatus));
            \App\Facades\Hooks::doAction('lead.status_changed', $lead, $previousStatus);
        }

        return back()->with('success', __('Enquiry updated.'));
    }

    /**
     * The enquiry form, optionally pre-filled with the property just viewed.
     */
    public function create(Request $request)
    {
        $this->ensureEnabled($request);

        return view('broker.leads.create', [
            'properties' => $this->assignedProperties($request),
            'selectedPropertyId' => $request->integer('property') ?: null,
            'statuses' => CustomFieldService::getOptions('lead_status'),
        ]);
    }

    /**
     * Record the enquiry as a lead.
     */
    public function store(Request $request)
    {
        $this->ensureEnabled($request);

        $broker = $request->user();

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:255',
            'visited_property_id' => 'nullable|integer',
            'status' => 'required|string|in:'.implode(',', CustomFieldService::getValidSlugs('lead_status')),
            'notes' => 'nullable|string|max:5000',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:'.self::MAX_PHOTO_KILOBYTES,
        ], [
            'photo.max' => __('The photo must be :max MB or smaller.', ['max' => round(self::MAX_PHOTO_KILOBYTES / 1024)]),
        ]);

        // Only a property actually shared with this broker may be attached —
        // otherwise the form would leak which other properties exist.
        $propertyId = $validated['visited_property_id'] ?? null;

        if ($propertyId !== null) {
            $propertyId = $this->assignedProperties($request)
                ->firstWhere('id', (int) $propertyId)?->id;
        }

        $lead = DB::transaction(function () use ($request, $broker, $validated, $propertyId) {
            $lead = Lead::create([
                'tenant_id' => $broker->tenant_id,
                'broker_id' => $broker->id,
                'visited_property_id' => $propertyId,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'] ?? '',
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
                'lead_source' => 'broker',
                'status' => $validated['status'],
                'temperature' => 'warm',
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($request->hasFile('photo')) {
                $file = $request->file('photo');

                $lead->clientPhoto()->create([
                    'tenant_id' => $broker->tenant_id,
                    'filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'content' => base64_encode(file_get_contents($file->getRealPath())),
                ]);
            }

            return $lead;
        });

        AuditLog::log('lead.created', $lead);

        // Same lifecycle hook the CRM's own lead creation fires, so workflows
        // and the automatic WhatsApp message treat a broker enquiry like any
        // other new lead.
        \App\Facades\Hooks::doAction('lead.created', $lead);

        return redirect()->route('broker.leads.index')
            ->with('success', __('Enquiry recorded. The team can see it in the CRM.'));
    }

    /**
     * The broker portal is a feature the platform owner grants per client;
     * when it is off, its enquiry screens go with it.
     */
    protected function ensureEnabled(Request $request): void
    {
        abort_unless($request->user()->tenant?->broker_portal_enabled, 404);
    }

    /**
     * Properties this broker may reference on an enquiry.
     *
     * @return \Illuminate\Support\Collection<int, Property>
     */
    protected function assignedProperties(Request $request)
    {
        return Property::query()
            ->visibleToBroker($request->user())
            ->orderBy('address')
            ->get(['id', 'address', 'city', 'state']);
    }
}
