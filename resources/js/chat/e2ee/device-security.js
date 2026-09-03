import { fromBase64Url, toBase64Url } from './encoding.js';
import { initializeCrypto } from './sodium.js';

function normalizedProvisioning(provisioning) {
    return provisioning.map(item => ({
        conversation_id: Number(item.conversation_id),
        key_version: Number(item.key_version),
        wrapped_key: String(item.wrapped_key),
        wrapping_algorithm: String(item.wrapping_algorithm),
        format_version: Number(item.format_version),
    })).sort((left, right) => left.conversation_id - right.conversation_id || left.key_version - right.key_version);
}

function normalizedWrappedKeys(wrappedKeys) {
    return wrappedKeys.map(item => ({
        device_id: Number(item.device_id),
        wrapped_key: String(item.wrapped_key),
        wrapping_algorithm: String(item.wrapping_algorithm),
        format_version: Number(item.format_version),
    })).sort((left, right) => left.device_id - right.device_id);
}

export function deviceApprovalCanonical({ userId, approverDeviceId, targetDeviceId, challenge, provisioning }) {
    return JSON.stringify({
        v: 1,
        purpose: 'device_approval',
        user_id: Number(userId),
        approver_device_id: Number(approverDeviceId),
        target_device_id: Number(targetDeviceId),
        challenge: String(challenge),
        provisioning: normalizedProvisioning(provisioning),
    });
}

export function conversationRotationCanonical({ userId, deviceId, conversationId, keyVersion, wrappedKeys }) {
    return JSON.stringify({
        v: 1,
        purpose: 'conversation_rotation',
        user_id: Number(userId),
        device_id: Number(deviceId),
        conversation_id: Number(conversationId),
        key_version: Number(keyVersion),
        wrapped_keys: normalizedWrappedKeys(wrappedKeys),
    });
}

export async function signDeviceProof(canonical, privateSigningKey) {
    const sodium = await initializeCrypto();
    const signingKey = typeof privateSigningKey === 'string'
        ? fromBase64Url(sodium, privateSigningKey)
        : privateSigningKey;
    const signature = sodium.crypto_sign_detached(sodium.from_string(canonical), signingKey);

    return toBase64Url(sodium, signature);
}
