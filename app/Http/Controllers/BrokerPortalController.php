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
        $query = $this->visibleProperties($request);

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
        // Resolved through the same visibility rule as the list, so a broker
        // can't reach an unshared property by guessing its id.
        $property = $this->visibleProperties($request)
            ->whereKey($property->getKey())
            ->firstOrFail();

        return view('broker.show', compact('property'));
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
