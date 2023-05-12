<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class OfficeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => UserResource::make($this->user),
            'images' => ImageResource::collection($this->images),
            'tags' => TagResource::collection($this->tags),

            $this->merge(Arr::except(parent::toArray($request),[
                'user_id','created_at','updated_at','deleted_at'
            ]))
        ];
    }
}
