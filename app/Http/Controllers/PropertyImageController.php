<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyImage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PropertyImageController extends Controller
{
    /** Largest single upload accepted, in kilobytes. */
    public const MAX_KILOBYTES = 4096;

    /** Most images one property may hold. */
    public const MAX_PER_PROPERTY = 12;

    /**
     * Serve an image's bytes.
     *
     * Anyone who may see the property may see its photos — including brokers
     * it was shared with, who reach it through their portal rather than the
     * CRM. Cached hard: the bytes for a given id never change.
     */
    public function show(Request $request, PropertyImage $image): Response
    {
        $user = $request->user();
        $property = $image->property;

        abort_if($property === null, 404);

        if ($user->isBroker()) {
            abort_unless(
                Property::query()->visibleToBroker($user)->whereKey($property->getKey())->exists(),
                403
            );
        } else {
            $this->authorize('view', $property);
        }

        $bytes = $image->bytes();

        return response($bytes, 200, [
            'Content-Type' => $image->mime_type,
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'Content-Disposition' => 'inline; filename="'.addslashes($image->filename).'"',
        ]);
    }

    /**
     * Attach uploaded images to a property.
     */
    public function store(Request $request, Property $property)
    {
        $this->authorize('update', $property);

        $request->validate([
            'images' => 'required|array',
            'images.*' => 'file|mimes:jpg,jpeg,png,webp|max:'.self::MAX_KILOBYTES,
        ], [
            'images.*.max' => __('Each image must be :max MB or smaller.', ['max' => round(self::MAX_KILOBYTES / 1024)]),
        ]);

        $added = $this->attach($request->file('images'), $property);

        return redirect()->route('properties.edit', $property)->with(
            'success',
            $added > 0
                ? trans_choice(':count image added|:count images added', $added, ['count' => $added])
                : __('No images were added — this property already has the maximum of :max.', ['max' => self::MAX_PER_PROPERTY])
        );
    }

    /**
     * Remove an image.
     */
    public function destroy(PropertyImage $image)
    {
        $property = $image->property;
        $this->authorize('update', $property);

        $wasPrimary = $image->is_primary;
        $image->delete();

        // Never leave a property with photos but no primary one.
        if ($wasPrimary) {
            $property->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
        }

        return back()->with('success', __('Image removed.'));
    }

    /**
     * Make an image the one shown in listings.
     */
    public function makePrimary(PropertyImage $image)
    {
        $property = $image->property;
        $this->authorize('update', $property);

        $property->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return back()->with('success', __('Cover image updated.'));
    }

    /**
     * Store uploaded files against a property, respecting the per-property cap.
     *
     * @param  array<int, \Illuminate\Http\UploadedFile>|null  $files
     * @return int  How many were actually stored.
     */
    public function attach(?array $files, Property $property): int
    {
        if (empty($files)) {
            return 0;
        }

        $existing = $property->images()->count();
        $remaining = max(0, self::MAX_PER_PROPERTY - $existing);
        $added = 0;

        foreach (array_slice($files, 0, $remaining) as $file) {
            $property->images()->create([
                'tenant_id' => $property->tenant_id,
                'uploaded_by' => auth()->id(),
                'filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'content' => base64_encode(file_get_contents($file->getRealPath())),
                'sort_order' => $existing + $added,
                'is_primary' => $existing === 0 && $added === 0,
            ]);

            $added++;
        }

        return $added;
    }
}
