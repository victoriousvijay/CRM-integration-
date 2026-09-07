<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;

/**
 * The broker's own view of the CRM: only the properties an admin shared with
 * them, and nothing else. Brokers have no access to leads, deals or any other
 * CRM screen, so this deliberately does not extend the main app layout.
 */
class BrokerPortalController extends Controller
{
    /**
     * The properties available to this broker.
     */
    public function index(Request $request)
    {
        $this->ensureEnabled($request);

        $query = $this->visibleProperties($request)->with('primaryImage');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('address', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('zip_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('property_type')) {
            $query->where('property_type', $request->property_type);
        }

        $properties = $query->latest()->paginate(24)->withQueryString();

        return view('broker.index', compact('properties'));
    }

    /**
     * A single property's detail page.
     */
    public function show(Request $request, Property $property)
    {
        $this->ensureEnabled($request);

        // Resolved through the same visibility rule as the list, so a broker
        // can't reach an unshared property by guessing its id.
        $property = $this->visibleProperties($request)
            ->with('images')
            ->whereKey($property->getKey())
            ->firstOrFail();

        return view('broker.show', compact('property'));
    }

    /**
     * Refuse the portal outright when the platform owner has not given this
     * client the broker portal. Checked in the controller rather than on the
     * route so the whole feature — properties and enquiries alike — goes dark
     * together.
     */
    protected function ensureEnabled(Request $request): void
    {
        abort_unless($request->user()->tenant?->broker_portal_enabled, 404);
    }

    /**
     * Base query of properties the current user may see in the portal.
     *
     * Admins get the unfiltered list so they can check what brokers see
     * without needing a second account.
     */
    protected function visibleProperties(Request $request)
    {
        $user = $request->user();

        $query = Property::query();

        if (! $user->isAdmin()) {
            $query->visibleToBroker($user);
        }

        return $query;
    }
}
