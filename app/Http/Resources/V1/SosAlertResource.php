<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SosAlert */
class SosAlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'triggered_by' => new UserResource($this->whenLoaded('triggeredBy')),
            'recipient_ids' => $this->recipient_ids,
            'slack_user_name' => $this->slack_user_name,
            'created_at' => $this->created_at,
        ];
    }
}
