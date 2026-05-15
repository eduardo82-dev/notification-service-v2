<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\SendNotificationDTO;
use App\Enums\Channel;
use App\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SendNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', 'string', Rule::in(array_column(Channel::cases(), 'value'))],
            'message' => ['required', 'string', 'max:4000'],
            'recipient_ids' => ['required', 'array', 'min:1', 'max:10000'],
            'recipient_ids.*' => ['required', 'string', 'max:64'],
            'priority' => ['required', 'string', Rule::in(array_column(Priority::cases(), 'value'))],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ];
    }

    public function toDTO(): SendNotificationDTO
    {
        $validated = $this->validated();

        return new SendNotificationDTO(
            channel: Channel::from($validated['channel']),
            message: $validated['message'],
            recipientIds: $validated['recipient_ids'],
            priority: Priority::from($validated['priority']),
            idempotencyKey: $validated['idempotency_key'],
        );
    }
}
