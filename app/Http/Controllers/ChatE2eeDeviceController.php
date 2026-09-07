<?php

namespace App\Http\Controllers;

use App\Models\ChatE2eeDevice;
use App\Models\User;
use App\Services\Chat\E2eeDeviceService;
use App\Services\Chat\E2eeDeviceTrustService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatE2eeDeviceController extends Controller
{
    public function index(Request $request, E2eeDeviceService $devices): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'devices' => $devices->ownDevices($actor)->map(fn (ChatE2eeDevice $device) => $device->only([
                'id',
                'device_uuid',
                'device_name',
                'public_encryption_key',
                'public_signing_key',
                'key_fingerprint',
                'trusted_at',
                'revoked_at',
                'last_used_at',
                'created_at',
            ]))->values(),
        ]);
    }

    public function store(Request $request, E2eeDeviceService $devices): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $device = $devices->register($actor, $request->all());

        return response()->json([
            'device' => $device->only([
                'id',
                'device_uuid',
                'device_name',
                'public_encryption_key',
                'public_signing_key',
                'key_fingerprint',
                'trusted_at',
                'revoked_at',
            ]),
        ], $device->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(
        Request $request,
        ChatE2eeDevice $device,
        E2eeDeviceService $devices,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $revoked = $devices->revoke($actor, $device);

        return response()->json([
            'device' => $revoked->only(['id', 'device_uuid', 'revoked_at']),
        ]);
    }

    public function approvalPlan(
        Request $request,
        ChatE2eeDevice $device,
        E2eeDeviceTrustService $trust,
    ): JsonResponse {
        $validated = $request->validate([
            'approver_device_uuid' => ['required', 'string', 'max:64'],
        ]);
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($trust->approvalPlan($actor, $device, $validated['approver_device_uuid']));
    }

    public function approve(
        Request $request,
        ChatE2eeDevice $device,
        E2eeDeviceTrustService $trust,
    ): JsonResponse {
        $validated = $request->validate([
            'approver_device_uuid' => ['required', 'string', 'max:64'],
            'challenge' => ['required', 'string', 'max:128'],
            'provisioning' => ['present', 'array', 'max:500'],
            'provisioning.*.conversation_id' => ['required', 'integer', 'distinct'],
            'provisioning.*.key_version' => ['required', 'integer', 'min:1'],
            'provisioning.*.wrapped_key' => ['required', 'string', 'max:8192'],
            'provisioning.*.wrapping_algorithm' => ['required', 'string', 'max:50'],
            'provisioning.*.format_version' => ['required', 'integer', 'in:1'],
            'signature' => ['required', 'string', 'max:256'],
            'user_id' => ['prohibited'],
            'private_key' => ['prohibited'],
            'conversation_key' => ['prohibited'],
            'raw_key' => ['prohibited'],
        ]);
        /** @var User $actor */
        $actor = $request->user();
        $approved = $trust->approve(
            $actor,
            $device,
            $validated['approver_device_uuid'],
            $validated['challenge'],
            $validated['provisioning'],
            $validated['signature'],
        );

        return response()->json([
            'device' => $approved->only(['id', 'device_uuid', 'trusted_at', 'revoked_at']),
        ]);
    }

    public function restorationPlan(Request $request, ChatE2eeDevice $device, E2eeDeviceTrustService $trust): JsonResponse
    {
        $validated = $request->validate(['approver_device_uuid' => ['required', 'string', 'max:64']]);

        return response()->json($trust->restorationPlan($request->user(), $device, $validated['approver_device_uuid']));
    }

    public function restore(Request $request, ChatE2eeDevice $device, E2eeDeviceTrustService $trust): JsonResponse
    {
        $validated = $request->validate([
            'approver_device_uuid' => ['required', 'string', 'max:64'], 'challenge' => ['required', 'string', 'max:128'],
            'provisioning' => ['present', 'array', 'max:500'], 'provisioning.*.conversation_id' => ['required', 'integer', 'distinct'],
            'provisioning.*.key_version' => ['required', 'integer', 'min:1'], 'provisioning.*.wrapped_key' => ['required', 'string', 'max:8192'],
            'provisioning.*.wrapping_algorithm' => ['required', 'string', 'max:50'], 'provisioning.*.format_version' => ['required', 'integer', 'in:1'],
            'signature' => ['required', 'string', 'max:256'], 'user_id' => ['prohibited'], 'private_key' => ['prohibited'],
            'conversation_key' => ['prohibited'], 'raw_key' => ['prohibited'],
        ]);
        $restored = $trust->restore($request->user(), $device, $validated['approver_device_uuid'], $validated['challenge'],
            $validated['provisioning'], $validated['signature']);

        return response()->json(['device' => $restored->only(['id', 'device_uuid', 'trusted_at', 'revoked_at'])]);
    }
}
