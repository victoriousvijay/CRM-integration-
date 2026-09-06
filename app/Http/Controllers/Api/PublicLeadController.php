<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\LeadIngestController;
use Illuminate\Http\Request;

/**
 * POST /api/v1/public/leads
 *
 * The public, browser-safe lead intake endpoint for the embeddable form and
 * embed.js widget. Authenticated via an "embed" type credential (see
 * EmbedKeyMiddleware) — a token that can only create leads, never read
 * or modify existing tenant data, so it is safe to ship in client-side code.
 *
 * Tenant identity always comes from the verified embed credential
 * (request attribute "tenant"), never from any field in the request body.
 */
class PublicLeadController extends Controller
{
    public function store(Request $request, LeadIngestController $ingest)
    {
        // Basic anti-spam: a hidden honeypot field real users never fill in.
        if ($request->filled('website_url_confirm')) {
            return response()->json(['success' => true, 'message' => 'Lead created successfully.'], 201);
        }

        return $ingest->store($request);
    }
}
