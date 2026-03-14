<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProductResource
 *
 * IMPORTANT — image_url rule:
 *
 * We return the RAW stored path (e.g. "/storage/products/abc.jpg").
 * We do NOT call asset() or url() on it.
 *
 * Why: asset() uses APP_URL from .env → "http://localhost:8000".
 * The Flutter app runs on a physical device at a different IP and
 * can never reach "localhost". Returning the relative path lets
 * Flutter build the correct absolute URL itself using the server
 * IP it already knows from ApiClient.baseUrl.
 */
class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => (int)    $this->id,
            'name'             => (string) $this->name,
            'description'      => (string) ($this->description ?? ''),
            'price'            => (float)  $this->price,

            // Return the stored relative path as-is.
            // "/storage/products/abc.jpg" — Flutter prepends the server origin.
            // If null (no image uploaded), return null so Flutter shows placeholder.
            'image_url'        => $this->image_url ?: null,

            'category'         => $this->whenLoaded('category', fn () => [
                'id'   => (int)    $this->category->id,
                'name' => (string) $this->category->name,
                'icon' => $this->category->icon,
            ]),

            'rating'           => (float) ($this->rating           ?? 4.5),
            'review_count'     => (int)   ($this->review_count     ?? 0),
            'preparation_time' => (int)   ($this->preparation_time ?? 20),
            'calories'         => (int)   ($this->calories         ?? 0),
            'ingredients'      => $this->ingredients ?? [],
            'is_popular'       => (bool)  $this->is_popular,
            'is_available'     => (bool)  $this->is_available,
        ];
    }
}