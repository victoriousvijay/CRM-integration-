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
        $leads = Lead::where('broker_id', $request->user()->id)
            ->with(['visitedProperty', 'clientPhoto'])
            ->latest()
            ->paginate(20);

        return view('broker.leads.index', compact('leads'));
    }

    /**
     * The enquiry form, optionally pre-filled with the property just viewed.
     */
    public function create(Request $request)
    {
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
