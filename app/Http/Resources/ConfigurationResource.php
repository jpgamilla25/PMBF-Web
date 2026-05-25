<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConfigurationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'type' => $this->type ?? 'text',
            'group' => $this->group,
            'description' => $this->description,
            'options' => $this->options,
            'suffix' => $this->suffix,
            'sort_order' => $this->sort_order,
        ];
    }
}
