<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

/** @mixin LengthAwarePaginator */
class OccupantListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'pagination' => [
                'current_page' => $this->currentPage(),
                'per_page' => $this->perPage(),
                'total_count' => $this->total(),
                'total_pages' => (int) ceil($this->total() / $this->perPage()),
            ],
            'items' => OccupantResource::collection($this->items()),
        ];
    }
}
