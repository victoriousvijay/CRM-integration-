<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LeadPhotoController extends Controller
{
    /**
     * Serve a lead's client photo.
     *
     * The broker who logged the enquiry can always see it; everyone else needs
     * the same permission that lets them view the lead itself.
     */
    public function show(Request $request, Lead $lead): Response
    {
        $user = $request->user();

        if ($lead->broker_id !== $user->id) {
            $this->authorize('view', $lead);
        }

        $photo = $lead->clientPhoto;

        abort_if($photo === null, 404);

        $bytes = $photo->bytes();

        return response($bytes, 200, [
            'Content-Type' => $photo->mime_type,
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
