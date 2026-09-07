<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantLogoController extends Controller
{
    /**
     * Serve a tenant's logo.
     *
     * Public on purpose. A logo is a company's public mark — it already appears
     * on their website — and the pages that need it are themselves public: the
     * buyer portal, a hosted lead form, the offline page a service worker
     * renders before anything has authenticated. Gating it behind a session
     * would break exactly the screens a white-label brand is for.
     */
    public function show(Request $request, Tenant $tenant): Response
    {
        $logo = $tenant->logo()->first();

        abort_if($logo === null, 404);

        return response($logo->bytes(), 200, [
            'Content-Type' => $logo->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($logo->filename).'"',
            // The URL carries the logo's updated_at, so a new upload is a new
            // URL and this can be cached hard.
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
