<?php

namespace App\Http\Controllers;

use App\Http\Requests\PropertyRequest;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\Property;
use App\Services\AddressNormalizationService;
use App\Services\CustomFieldService;
use App\Services\ZipTimezoneService;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    /**
     * Display a listing of properties.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Property::class);

        $query = Property::with(['lead', 'primaryImage'])->withCount('brokers');

        if (auth()->user()->isAgent()) {
            $query->whereHas('lead', function ($q) {
                $q->where('agent_id', auth()->id());
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('address', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('zip_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('property_type')) {
            $query->where('property_type', $request->property_type);
        }

        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }

        if ($request->filled('distress')) {
            $distressFilters = (array) $request->distress;
            foreach ($distressFilters as $marker) {
                $query->whereJsonContains('distress_markers', $marker);
            }
        }

        if ($request->filled('listing_status')) {
            $query->where('listing_status', $request->listing_status);
        }

        $properties = $query->latest()->paginate(25);

        return view('properties.index', compact('properties'));
    }

    /**
     * Store a property for a lead (AJAX or form).
     */
    public function store(PropertyRequest $request, Lead $lead)
    {
        $this->authorize('update', $lead);
        $this->authorize('create', Property::class);

        $data = $request->validated();
        $data = AddressNormalizationService::normalizeAll($data);
        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['lead_id'] = $lead->id;

        // Compute MAO: (ARV x 0.70) - Repair Estimate (wholesale only)
        if (!\App\Services\BusinessModeService::isRealEstate()
            && !empty($data['after_repair_value']) && !empty($data['repair_estimate'])) {
            $data['maximum_allowable_offer'] = ($data['after_repair_value'] * 0.70) - $data['repair_estimate'];
        }

        $property = Property::updateOrCreate(
            ['lead_id' => $lead->id],
            $data
        );

        AuditLog::log('property.created', $property);

        // Auto-detect lead timezone from property zip code
        $timezone = ZipTimezoneService::detect($property->zip_code);
        if ($timezone && !$lead->timezone) {
            $lead->update(['timezone' => $timezone]);
        }

        if ($request->ajax()) {
            return response()->json(['success' => true, 'property' => $property]);
        }

        return redirect()->route('leads.show', $lead)->with('success', 'Property saved successfully.');
    }

    /**
     * Show the form for creating a property that isn't tied to a lead.
     *
     * Properties could previously only be created from a lead's detail page,
     * which left no way to record a listing the brokerage holds directly.
     */
    public function create()
    {
        $this->authorize('create', Property::class);

        return view('properties.create', ['brokers' => $this->brokers()]);
    }

    /**
     * Store a standalone property.
     */
    public function storeStandalone(PropertyRequest $request)
    {
        $this->authorize('create', Property::class);

        $data = $this->prepare($request->validated());

        $property = Property::create($data);
        $this->syncBrokerAccess($request, $property);

        if ($request->hasFile('images')) {
            $request->validate([
                'images.*' => 'file|mimes:jpg,jpeg,png,webp|max:'.PropertyImageController::MAX_KILOBYTES,
            ]);

            app(PropertyImageController::class)->attach($request->file('images'), $property);
        }

        AuditLog::log('property.created', $property);

        return redirect()->route('properties.show', $property)
            ->with('success', __('Property created successfully.'));
    }

    /**
     * Show the form for editing a property.
     */
    public function edit(Property $property)
    {
        $this->authorize('update', $property);

        $property->load(['brokers', 'images']);

        return view('properties.edit', ['property' => $property, 'brokers' => $this->brokers()]);
    }

    /**
     * Update a property.
     */
    public function update(PropertyRequest $request, Property $property)
    {
        $this->authorize('update', $property);

        $property->update($this->prepare($request->validated()));
        $this->syncBrokerAccess($request, $property);

        AuditLog::log('property.updated', $property);

        return redirect()->route('properties.show', $property)
            ->with('success', __('Property updated successfully.'));
    }

    /**
     * Delete a property.
     */
    public function destroy(Property $property)
    {
        $this->authorize('delete', $property);

        AuditLog::log('property.deleted', $property);

        $property->delete();

        return redirect()->route('properties.index')
            ->with('success', __('Property deleted.'));
    }

    /**
     * Brokers in this tenant who a property can be shared with.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\User>
     */
    protected function brokers()
    {
        return \App\Models\User::whereHas('role', fn ($q) => $q->where('name', 'broker'))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Apply the submitted broker access choice to a property.
     *
     * Three states, and only an admin may change them: shared with nobody,
     * shared with every broker (including ones added later, hence the flag
     * rather than filling the pivot), or shared with a named few.
     */
    protected function syncBrokerAccess(Request $request, Property $property): void
    {
        if (! auth()->user()->isAdmin()) {
            return;
        }

        $mode = $request->input('broker_access', 'none');

        if ($mode === 'all') {
            $property->update(['shared_with_all_brokers' => true]);
            $property->brokers()->detach();

            return;
        }

        $property->update(['shared_with_all_brokers' => false]);

        if ($mode !== 'selected') {
            $property->brokers()->detach();

            return;
        }

        $brokerIds = $this->brokers()
            ->whereIn('id', (array) $request->input('broker_ids', []))
            ->pluck('id');

        $property->brokers()->sync(
            $brokerIds->mapWithKeys(fn ($id) => [$id => [
                'tenant_id' => $property->tenant_id,
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
            ]])->all()
        );
    }

    /**
     * Normalize submitted property data and derive anything computed from it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data): array
    {
        $data = AddressNormalizationService::normalizeAll($data);
        $data['tenant_id'] = auth()->user()->tenant_id;

        // MAO: (ARV x 0.70) - repair estimate. Wholesale-only arithmetic, so
        // it stays out of a real estate tenant's records entirely.
        if (! \App\Services\BusinessModeService::isRealEstate()
            && ! empty($data['after_repair_value']) && ! empty($data['repair_estimate'])) {
            $data['maximum_allowable_offer'] = ($data['after_repair_value'] * 0.70) - $data['repair_estimate'];
        }

        return $data;
    }

    /**
     * Store a property submitted by a field scout (standalone, no lead required).
     */
    public function fieldScoutStore(Request $request)
    {
        $this->authorize('createFieldScout', Property::class);

        $data = $request->validate([
            'address'          => 'required|string|max:255',
            'city'             => 'required|string|max:100',
            'state'            => 'required|string|max:2',
            'zip_code'         => 'required|string|max:10',
            'property_type'    => 'required|in:' . implode(',', CustomFieldService::getValidSlugs('property_type')),
            'distress_markers' => 'nullable|array',
            'distress_markers.*' => 'string|in:' . implode(',', CustomFieldService::getValidSlugs('distress_markers')),
            'notes'            => 'nullable|string|max:2000',
        ]);

        $data = AddressNormalizationService::normalizeAll($data);
        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['distress_markers'] = $data['distress_markers'] ?? [];

        $property = Property::create($data);

        AuditLog::log('property.created', $property);

        return redirect()->route('dashboard')->with('success', 'Property submitted successfully.');
    }

    /**
     * Show a property detail page.
     */
    public function show(Property $property)
    {
        $this->authorize('view', $property);
        $property->load(['lead', 'images']);

        return view('properties.show', compact('property'));
    }
}
