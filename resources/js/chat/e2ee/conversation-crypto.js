import { fromBase64Url, toBase64Url } from './encoding.js';
import { initializeCrypto } from './sodium.js';

export const ENVELOPE_VERSION = 1;
export const ENCRYPTION_ALGORITHM = 'xchacha20poly1305-ietf';
export const WRAPPING_ALGORITHM = 'x25519-sealedbox';

export async function generateConversationKey() {
    const sodium = await initializeCrypto();

    return sodium.crypto_aead_xchacha20poly1305_ietf_keygen();
}

export async function generateClientMessageUuid() {
    const sodium = await initializeCrypto();
    const bytes = sodium.randombytes_buf(16);
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (value) => value.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export async function wrapConversationKeyForDevice(conversationKey, publicEncryptionKey) {
    const sodium = await initializeCrypto();
    const publicKey = typeof publicEncryptionKey === 'string'
        ? fromBase64Url(sodium, publicEncryptionKey)
        : publicEncryptionKey;

    return toBase64Url(sodium, sodium.crypto_box_seal(conversationKey, publicKey));
}

export async function unwrapConversationKey(wrappedKey, publicEncryptionKey, privateEncryptionKey) {
    const sodium = await initializeCrypto();
    const opened = sodium.crypto_box_seal_open(
        fromBase64Url(sodium, wrappedKey),
        typeof publicEncryptionKey === 'string' ? fromBase64Url(sodium, publicEncryptionKey) : publicEncryptionKey,
        typeof privateEncryptionKey === 'string' ? fromBase64Url(sodium, privateEncryptionKey) : privateEncryptionKey,
    );

    if (!opened) {
        throw new Error('Unable to unwrap the E2EE conversation key.');
    }

    return opened;
}

export function associatedData({ conversationId, clientMessageUuid, senderId, keyVersion }) {
    if (!conversationId || !clientMessageUuid || !senderId || !Number.isInteger(keyVersion) || keyVersion < 1) {
        throw new Error('Complete immutable E2EE associated-data fields are required.');
    }

    return `brahmabull:e2ee:v1|conversation:${conversationId}|client_message:${clientMessageUuid}|sender:${senderId}|key_version:${keyVersion}`;
}

export async function encryptPayload({ plaintext, key, conversationId, clientMessageUuid, senderId, keyVersion, associatedDataOverride = null }) {
    const sodium = await initializeCrypto();
    const nonce = sodium.randombytes_buf(sodium.crypto_aead_xchacha20poly1305_ietf_NPUBBYTES);
    const aad = associatedDataOverride || associatedData({ conversationId, clientMessageUuid, senderId, keyVersion });
    const ciphertext = sodium.crypto_aead_xchacha20poly1305_ietf_encrypt(
        sodium.from_string(plaintext),
        sodium.from_string(aad),
        null,
        nonce,
        key,
    );

    return {
        v: ENVELOPE_VERSION,
        alg: ENCRYPTION_ALGORITHM,
        key_version: keyVersion,
        nonce: toBase64Url(sodium, nonce),
        ciphertext: toBase64Url(sodium, ciphertext),
    };
}

export async function decryptPayload({ envelope, key, conversationId, clientMessageUuid, senderId, associatedDataOverride = null }) {
    const sodium = await initializeCrypto();

    if (envelope?.v !== ENVELOPE_VERSION
        || envelope?.alg !== ENCRYPTION_ALGORITHM
        || !Number.isInteger(envelope?.key_version)
        || envelope.key_version < 1) {
        throw new Error('Unsupported E2EE envelope.');
    }

    const aad = associatedDataOverride || associatedData({
        conversationId,
        clientMessageUuid,
        senderId,
        keyVersion: envelope.key_version,
    });
    const plaintext = sodium.crypto_aead_xchacha20poly1305_ietf_decrypt(
        null,
        fromBase64Url(sodium, envelope.ciphertext),
        sodium.from_string(aad),
        fromBase64Url(sodium, envelope.nonce),
        key,
    );

    if (!plaintext) {
        throw new Error('E2EE payload authentication failed.');
    }

    return sodium.to_string(plaintext);
}

export async function encryptedMessageRequest({ plaintext, key, conversationId, senderId, keyVersion, replyToMessageId = null, clientMessageUuid = null }) {
    clientMessageUuid ||= await generateClientMessageUuid();
    const envelope = await encryptPayload({
        plaintext,
        key,
        conversationId,
        clientMessageUuid,
        senderId,
        keyVersion,
    });

    return {
        client_message_uuid: clientMessageUuid,
        encrypted_payload: JSON.stringify(envelope),
        encryption_version: envelope.v,
        key_version: envelope.key_version,
        reply_to_message_id: replyToMessageId,
    };
}
