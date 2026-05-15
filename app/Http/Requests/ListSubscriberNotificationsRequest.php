<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\NotificationFilterDTO;
use App\Enums\Channel;
use App\Enums\NotificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListSubscriberNotificationsRequest extends FormRequest
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
            'status' => ['nullable', 'string', Rule::in(array_column(NotificationStatus::cases(), 'value'))],
            'channel' => ['nullable', 'string', Rule::in(array_column(Channel::cases(), 'value'))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function toDTO(): NotificationFilterDTO
    {
        $validated = $this->validated();

        return new NotificationFilterDTO(
            subscriberId: $this->route('subscriberId'),
            channel: isset($validated['channel']) ? Channel::from($validated['channel']) : null,
            status: isset($validated['status']) ? NotificationStatus::from($validated['status']) : null,
            perPage: (int) ($validated['per_page'] ?? 20),
        );
    }
}
