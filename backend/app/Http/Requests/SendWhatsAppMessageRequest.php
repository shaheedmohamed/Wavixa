<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendWhatsAppMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Authorization is handled by the VerifyApiKey middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $route = $this->route()->getName() ?? $this->route()->uri();

        $baseRules = [
            'to' => ['required', 'string', 'min:8', 'max:20'],
            'scheduled_at' => ['nullable', 'date', 'after:now', 'before:' . now()->addDays(365)->toDateTimeString()],
            'delay_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
        ];

        if (str_contains($route, 'send-message')) {
            return array_merge($baseRules, [
                'message' => ['required', 'string', 'max:4000'],
            ]);
        }

        if (str_contains($route, 'send-image')) {
            return array_merge($baseRules, [
                'content' => ['required_without:image_url', 'nullable', 'string'],
                'image_url' => ['required_without:content', 'nullable', 'string'],
                'caption' => ['nullable', 'string', 'max:1000'],
                'file_name' => ['nullable', 'string', 'max:255'],
                'filename' => ['nullable', 'string', 'max:255'],
                'mimetype' => ['nullable', 'string', 'max:100'],
            ]);
        }

        if (str_contains($route, 'send-file')) {
            return array_merge($baseRules, [
                'content' => ['required_without:file_url', 'nullable', 'string'],
                'file_url' => ['required_without:content', 'nullable', 'string'],
                'file_name' => ['nullable', 'string', 'max:255'],
                'filename' => ['nullable', 'string', 'max:255'],
                'mimetype' => ['nullable', 'string', 'max:100'],
                'caption' => ['nullable', 'string', 'max:1000'],
            ]);
        }

        if (str_contains($route, 'send-video')) {
            return array_merge($baseRules, [
                'content' => ['required_without:video_url', 'nullable', 'string'],
                'video_url' => ['required_without:content', 'nullable', 'string'],
                'caption' => ['nullable', 'string', 'max:1000'],
                'file_name' => ['nullable', 'string', 'max:255'],
                'filename' => ['nullable', 'string', 'max:255'],
                'mimetype' => ['nullable', 'string', 'max:100'],
            ]);
        }

        if (str_contains($route, 'send-voice')) {
            return array_merge($baseRules, [
                'content' => ['required_without:audio_url', 'nullable', 'string'],
                'audio_url' => ['required_without:content', 'nullable', 'string'],
                'mimetype' => ['nullable', 'string', 'max:100'],
            ]);
        }

        if (str_contains($route, 'send-contact')) {
            return array_merge($baseRules, [
                'content' => ['required', 'array'],
                'content.display_name' => ['required', 'string', 'max:255'],
                'content.phone' => ['required', 'string', 'min:8', 'max:20'],
            ]);
        }

        if (str_contains($route, 'send-location')) {
            return array_merge($baseRules, [
                'content' => ['required', 'array'],
                'content.latitude' => ['required', 'numeric', 'between:-90,90'],
                'content.longitude' => ['required', 'numeric', 'between:-180,180'],
                'content.name' => ['nullable', 'string', 'max:255'],
                'content.address' => ['nullable', 'string', 'max:1000'],
            ]);
        }

        // Fallback for any other message endpoint
        return array_merge($baseRules, [
            'message' => ['required', 'string', 'max:4000'],
        ]);
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'to.required' => 'Phone number is required.',
            'to.min' => 'Phone number must be at least 8 characters.',
            'to.max' => 'Phone number must not exceed 20 characters.',
            'content.required' => 'Content is required for this message type.',
            'message.required' => 'Message text is required.',
            'content.latitude.required' => 'Latitude is required for location messages.',
            'content.latitude.between' => 'Latitude must be between -90 and 90.',
            'content.longitude.required' => 'Longitude is required for location messages.',
            'content.longitude.between' => 'Longitude must be between -180 and 180.',
            'content.display_name.required' => 'Contact display name is required.',
            'content.phone.required' => 'Contact phone number is required.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Validation\ValidationException($validator, response()->json([
            'success' => false,
            'message' => 'Validation error',
            'errors' => $validator->errors(),
        ], 422));
    }
}
