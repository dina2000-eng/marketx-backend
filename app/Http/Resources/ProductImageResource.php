<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductImageResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'image_url' => url('storage/'.$this->image_url),

            'is_main' => $this->is_main,

            'sort_order' => $this->sort_order,

        ];
    }

}