<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatE2eeDevice;
use App\Models\User;
use App\Services\Chat\E2eeActivationService;
use App\Services\Chat\E2eeConversationKeyService;
use App\Services\Chat\E2eeDeviceService;
use App\Services\Chat\E2eeDowngradeService;
use App\Services\Chat\E2eeRotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatE2eeConversationController extends Controller
{
    public function activate(
        Request $request,
        ChatConversation $conversation,
        E2eeActivationService $activation,
    ): JsonResponse {
        $validated = $request->validate([
            'key_version' => ['required', 'integer', 'min:1'],
            'wrapped_keys' => ['required', 'array', 'min:2', 'max:50'],
            'wrapped_keys.*.device_id' => ['required', 'integer', 'distinct'],
            'wrapped_keys.*.wrapped_key' => ['required', 'string', 'max:8192'],
            'wrapped_keys.*.wrapping_algorithm' => ['required', 'string', 'max:50'],
            'wrapped_keys.*.format_version' => ['required', 'integer', 'in:1'],
            'conversation_key' => ['prohibited'],
            'raw_key' => ['prohibited'],
            'private_key' => ['prohibited'],
            'plaintext' => ['prohibited'],
        ]);
        /** @var User $actor */
        $actor = $request->user();
        $conversation = $activation->activate(
            $conversation,
            $actor,
            (int) $validated['key_version'],
            $validated['wrapped_keys'],
        );

        return response()->json([
            'conversation' => $conversation->only(['id', 'encryption_mode', 'e2ee_enabled_at', 'current_key_version']),
        ]);
    }

    public function requestDisable(
        Request $request,
        ChatConversation $conversation,
        E2eeDowngradeService $downgrade,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'conversation' => $downgrade->requestDisable($conversation, $actor),
        ]);
    }

    public function keepEncryption(
        Request $request,
        ChatConversation $conversation,
        E2eeDowngradeService $downgrade,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'conversation' => $downgrade->keepEncryption($conversation, $actor),
        ]);
    }

    public function approveDisable(
        Request $request,
        ChatConversation $conversation,
        E2eeDowngradeService $downgrade,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'conversation' => $downgrade->approveDisable($conversation, $actor),
        ]);
    }

    public function wrappedKey(
        Request $request,
        ChatConversation $conversation,
        E2eeConversationKeyService $keys,
    ): JsonResponse {
        $validated = $request->validate([
            'device_uuid' => ['required', 'string', 'max:64'],
            'key_version' => ['nullable', 'integer', 'min:1'],
        ]);
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'key' => $keys->wrappedKeyForOwnDevice(
                $conversation,
                $actor,
                $validated['device_uuid'],
                $validated['key_version'] ?? null,
            ),
        ]);
    }

    public function rotate(
        Request $request,
        ChatConversation $conversation,
        E2eeRotationService $rotation,
    ): JsonResponse {
        $validated = $request->validate([
            'initiator_device_uuid' => ['required', 'string', 'max:64'],
            'key_version' => ['required', 'integer', 'min:2'],
            'wrapped_keys' => ['required', 'array', 'min:2', 'max:50'],
            'wrapped_keys.*.device_id' => ['required', 'integer', 'distinct'],
            'wrapped_keys.*.wrapped_key' => ['required', 'string', 'max:8192'],
            'wrapped_keys.*.wrapping_algorithm' => ['required', 'string', 'max:50'],
            'wrapped_keys.*.format_version' => ['required', 'integer', 'in:1'],
            'signature' => ['required', 'string', 'max:256'],
            'user_id' => ['prohibited'],
            'conversation_key' => ['prohibited'],
            'raw_key' => ['prohibited'],
            'private_key' => ['prohibited'],
        ]);
        /** @var User $actor */
        $actor = $request->user();
        $conversation = $rotation->rotate(
            $conversation,
            $actor,
            $validated['initiator_device_uuid'],
            $validated['key_version'],
            $validated['wrapped_keys'],
            $validated['signature'],
        );

        return response()->json([
            'conversation' => $conversation->only([
                'id', 'encryption_mode', 'current_key_version', 'e2ee_rotation_required_at',
            ]),
        ]);
    }

    public function devices(
        Request $request,
        ChatConversation $conversation,
        E2eeDeviceService $devices,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'devices' => $devices->publicDevicesForConversation($conversation, $actor)->values(),
        ]);
    }

    public function storeKey(
        Request $request,
        ChatConversation $conversation,
        E2eeConversationKeyService $keys,
    ): JsonResponse {
        $validated = $request->validate([
            'device_id' => ['required', 'integer'],
            'key_version' => ['required', 'integer', 'min:1'],
            'wrapped_key' => ['required', 'string', 'max:8192'],
            'wrapping_algorithm' => ['required', 'string', 'max:50'],
            'format_version' => ['required', 'integer'],
            'user_id' => ['prohibited'],
            'conversation_key' => ['prohibited'],
            'raw_key' => ['prohibited'],
            'symmetric_key' => ['prohibited'],
            'private_key' => ['prohibited'],
        ]);
        /** @var User $actor */
        $actor = $request->user();
        $device = ChatE2eeDevice::findOrFail($validated['device_id']);
        $key = $keys->storeWrappedKey(
            $conversation,
            $actor,
            $device,
            $validated['key_version'],
            $validated['wrapped_key'],
            $validated['wrapping_algorithm'],
            $validated['format_version'],
        );

        return response()->json([
            'key' => $key->only([
                'id',
                'conversation_id',
                'device_id',
                'key_version',
                'wrapping_algorithm',
                'format_version',
                'created_at',
            ]),
        ], $key->wasRecentlyCreated ? 201 : 200);
    }

    public function nextVersion(
        Request $request,
        ChatConversation $conversation,
        E2eeConversationKeyService $keys,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'next_key_version' => $keys->nextProvisioningVersion($conversation, $actor),
        ]);
    }
}
