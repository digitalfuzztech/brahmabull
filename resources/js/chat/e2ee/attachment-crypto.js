import { concatBytes, fromBase64Url, toBase64Url } from './encoding.js';
import { ENCRYPTION_ALGORITHM, ENVELOPE_VERSION, generateClientMessageUuid } from './conversation-crypto.js';
import { initializeCrypto } from './sodium.js';

export const E2EE_ATTACHMENT_PLAINTEXT_MAX_BYTES = 1792000;

const ALLOWED = {
    image: {
        extensions: ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    },
    video: {
        extensions: ['mp4', 'webm'],
        mimeTypes: ['video/mp4', 'video/webm'],
    },
    audio: {
        extensions: ['mp3', 'm4a', 'wav', 'ogg'],
        mimeTypes: ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'application/ogg'],
    },
    document: {
        extensions: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'],
        mimeTypes: [
            'application/pdf', 'text/plain', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
    },
};

export function attachmentAssociatedData({ conversationId, clientMessageUuid, clientAttachmentUuid, senderId, keyVersion, purpose }) {
    if (!conversationId || !clientMessageUuid || !clientAttachmentUuid || !senderId
        || !Number.isInteger(keyVersion) || keyVersion < 1
        || !['key', 'metadata', 'file'].includes(purpose)) {
        throw new Error('Complete E2EE attachment associated-data fields are required.');
    }

    return `brahmabull:e2ee:attachment:v1|conversation:${conversationId}|client_message:${clientMessageUuid}|attachment:${clientAttachmentUuid}|sender:${senderId}|key_version:${keyVersion}|purpose:${purpose}`;
}

function safeMetadata(name, mimeType, size) {
    const originalName = String(name || '').split(/[\\/]/).pop().slice(0, 255);
    const extension = originalName.includes('.') ? originalName.split('.').pop().toLowerCase() : '';
    const mediaType = Object.entries(ALLOWED).find(([, rules]) => rules.extensions.includes(extension) && rules.mimeTypes.includes(mimeType))?.[0];

    if (!originalName || !mediaType) throw new Error('This file type is not allowed for encrypted chat.');
    if (!Number.isInteger(size) || size < 1 || size > E2EE_ATTACHMENT_PLAINTEXT_MAX_BYTES) {
        throw new Error('Encrypted attachments may not exceed 1,750 KiB.');
    }

    return { original_name: originalName, mime_type: mimeType, media_type: mediaType, plaintext_size: size };
}

async function encryptEnvelope(bytes, key, aad, keyVersion) {
    const sodium = await initializeCrypto();
    const nonce = sodium.randombytes_buf(sodium.crypto_aead_xchacha20poly1305_ietf_NPUBBYTES);
    const ciphertext = sodium.crypto_aead_xchacha20poly1305_ietf_encrypt(bytes, sodium.from_string(aad), null, nonce, key);

    return {
        v: ENVELOPE_VERSION,
        alg: ENCRYPTION_ALGORITHM,
        key_version: keyVersion,
        nonce: toBase64Url(sodium, nonce),
        ciphertext: toBase64Url(sodium, ciphertext),
    };
}

async function decryptEnvelope(envelope, key, aad) {
    const sodium = await initializeCrypto();
    const plaintext = sodium.crypto_aead_xchacha20poly1305_ietf_decrypt(
        null,
        fromBase64Url(sodium, envelope.ciphertext),
        sodium.from_string(aad),
        fromBase64Url(sodium, envelope.nonce),
        key,
    );
    if (!plaintext) throw new Error('Encrypted attachment authentication failed.');

    return plaintext;
}

export async function encryptAttachment({ bytes, name, mimeType, conversationKey, conversationId, clientMessageUuid, senderId, keyVersion }) {
    const sodium = await initializeCrypto();
    const plaintext = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
    const metadata = safeMetadata(name, mimeType, plaintext.byteLength);
    const clientAttachmentUuid = await generateClientMessageUuid();
    const attachmentKey = sodium.crypto_aead_xchacha20poly1305_ietf_keygen();
    const common = { conversationId, clientMessageUuid, clientAttachmentUuid, senderId, keyVersion };
    const encryptedKey = await encryptEnvelope(
        attachmentKey,
        conversationKey,
        attachmentAssociatedData({ ...common, purpose: 'key' }),
        keyVersion,
    );
    const encryptedMetadata = await encryptEnvelope(
        sodium.from_string(JSON.stringify(metadata)),
        attachmentKey,
        attachmentAssociatedData({ ...common, purpose: 'metadata' }),
        keyVersion,
    );
    const fileNonce = sodium.randombytes_buf(sodium.crypto_aead_xchacha20poly1305_ietf_NPUBBYTES);
    const fileCiphertext = sodium.crypto_aead_xchacha20poly1305_ietf_encrypt(
        plaintext,
        sodium.from_string(attachmentAssociatedData({ ...common, purpose: 'file' })),
        null,
        fileNonce,
        attachmentKey,
    );

    return {
        client_attachment_uuid: clientAttachmentUuid,
        ciphertext: concatBytes(fileNonce, fileCiphertext),
        encrypted_key: JSON.stringify(encryptedKey),
        encrypted_metadata: JSON.stringify(encryptedMetadata),
        encryption_version: ENVELOPE_VERSION,
        key_version: keyVersion,
    };
}

export async function decryptAttachment({ ciphertext, encryptedKey, encryptedMetadata, conversationKey, conversationId, clientMessageUuid, clientAttachmentUuid, senderId, keyVersion }) {
    const sodium = await initializeCrypto();
    const { attachmentKey, metadata } = await decryptAttachmentMetadata({
        encryptedKey, encryptedMetadata, conversationKey, conversationId, clientMessageUuid, clientAttachmentUuid, senderId, keyVersion,
    });
    const common = { conversationId, clientMessageUuid, clientAttachmentUuid, senderId, keyVersion };

    const encryptedBytes = ciphertext instanceof Uint8Array ? ciphertext : new Uint8Array(ciphertext);
    const nonceBytes = sodium.crypto_aead_xchacha20poly1305_ietf_NPUBBYTES;
    const plaintext = sodium.crypto_aead_xchacha20poly1305_ietf_decrypt(
        null,
        encryptedBytes.slice(nonceBytes),
        sodium.from_string(attachmentAssociatedData({ ...common, purpose: 'file' })),
        encryptedBytes.slice(0, nonceBytes),
        attachmentKey,
    );
    if (!plaintext) throw new Error('Encrypted attachment authentication failed.');

    if (plaintext.byteLength !== metadata.plaintext_size) throw new Error('Encrypted attachment size metadata is invalid.');

    return { bytes: plaintext, metadata };
}

export async function decryptAttachmentMetadata({ encryptedKey, encryptedMetadata, conversationKey, conversationId, clientMessageUuid, clientAttachmentUuid, senderId, keyVersion }) {
    const sodium = await initializeCrypto();
    const common = { conversationId, clientMessageUuid, clientAttachmentUuid, senderId, keyVersion };
    const attachmentKey = await decryptEnvelope(
        typeof encryptedKey === 'string' ? JSON.parse(encryptedKey) : encryptedKey,
        conversationKey,
        attachmentAssociatedData({ ...common, purpose: 'key' }),
    );
    const metadataBytes = await decryptEnvelope(
        typeof encryptedMetadata === 'string' ? JSON.parse(encryptedMetadata) : encryptedMetadata,
        attachmentKey,
        attachmentAssociatedData({ ...common, purpose: 'metadata' }),
    );
    const parsed = JSON.parse(sodium.to_string(metadataBytes));
    const metadata = safeMetadata(parsed.original_name, parsed.mime_type, parsed.plaintext_size);
    if (metadata.media_type !== parsed.media_type) throw new Error('Encrypted attachment type metadata is invalid.');

    return { attachmentKey, metadata };
}
