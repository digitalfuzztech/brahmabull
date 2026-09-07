import assert from 'node:assert/strict';
import test from 'node:test';

import {
    createDeviceIdentity,
    conversationRotationCanonical,
    decryptAttachment,
    decryptAttachmentMetadata,
    decryptPayload,
    decryptReaction,
    deviceApprovalCanonical,
    deviceRestorationCanonical,
    encryptAttachment,
    encryptReaction,
    encryptedMessageRequest,
    encryptPayload,
    generateClientMessageUuid,
    generateConversationKey,
    groupDecryptedReactions,
    initializeCrypto,
    registrationPayload,
    selectDeviceIdentity,
    signDeviceProof,
    unwrapConversationKey,
    wrapConversationKeyForDevice,
} from '../../resources/js/chat/e2ee/index.js';

globalThis.window ??= {};
globalThis.document ??= { querySelector: () => ({ content: 'test-csrf-token' }) };

const {
    createE2eeDeviceManager,
    createTeamE2eeMessenger,
    nextActivationKeyVersion,
} = await import('../../resources/js/chat/e2ee/team-messenger.js');

test('device registration payload contains only public material', async () => {
    const identity = await createDeviceIdentity('Test Browser');
    const payload = registrationPayload(identity);

    assert.equal(Object.hasOwn(payload, 'private_encryption_key'), false);
    assert.equal(Object.hasOwn(payload, 'private_signing_key'), false);
    assert.equal(JSON.stringify(payload).includes(identity.privateEncryptionKey), false);
    assert.equal(JSON.stringify(payload).includes(identity.privateSigningKey), false);
});

test('browser device identities stay scoped to the authenticated staff account', () => {
    const legacy = {
        deviceUuid: 'legacy-device',
        keyFingerprint: 'legacy-fingerprint',
    };
    const otherAccount = {
        ...legacy,
        ownerUserId: 7,
    };

    assert.equal(selectDeviceIdentity([otherAccount], 9, []), null);
    assert.equal(selectDeviceIdentity([otherAccount], 7, []), otherAccount);

    const adopted = selectDeviceIdentity([legacy], 9, [{
        device_uuid: 'legacy-device',
        key_fingerprint: 'legacy-fingerprint',
    }]);

    assert.equal(adopted.ownerUserId, 9);
    assert.equal(adopted.deviceUuid, 'legacy-device');
});

test('Laravel-facing encrypted message payload contains ciphertext metadata only', async () => {
    const plaintext = 'This must remain in browser memory';
    const payload = await encryptedMessageRequest({
        plaintext,
        key: await generateConversationKey(),
        conversationId: 7,
        senderId: 11,
        keyVersion: 1,
        replyToMessageId: 19,
    });

    assert.deepEqual(Object.keys(payload).sort(), [
        'client_message_uuid',
        'encrypted_payload',
        'encryption_version',
        'key_version',
        'reply_to_message_id',
    ]);
    assert.equal(JSON.stringify(payload).includes(plaintext), false);
    assert.equal(Object.hasOwn(payload, 'body'), false);
    assert.equal(Object.hasOwn(payload, 'message'), false);
});

test('sealed conversation keys unwrap only with the intended device', async () => {
    const intended = await createDeviceIdentity();
    const wrong = await createDeviceIdentity();
    const key = await generateConversationKey();
    const wrapped = await wrapConversationKeyForDevice(key, intended.publicEncryptionKey);
    const opened = await unwrapConversationKey(
        wrapped,
        intended.publicEncryptionKey,
        intended.privateEncryptionKey,
    );

    assert.deepEqual(opened, key);
    await assert.rejects(() => unwrapConversationKey(
        wrapped,
        wrong.publicEncryptionKey,
        wrong.privateEncryptionKey,
    ));

    const tampered = `${wrapped.slice(0, -1)}${wrapped.endsWith('A') ? 'B' : 'A'}`;
    await assert.rejects(() => unwrapConversationKey(
        tampered,
        intended.publicEncryptionKey,
        intended.privateEncryptionKey,
    ));
});

test('XChaCha20-Poly1305 authenticates ciphertext nonce key and associated data', async () => {
    const key = await generateConversationKey();
    const wrongKey = await generateConversationKey();
    const clientMessageUuid = await generateClientMessageUuid();
    assert.match(clientMessageUuid, /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
    const parameters = {
        plaintext: 'Browser-only plaintext',
        key,
        conversationId: 42,
        clientMessageUuid,
        senderId: 9,
        keyVersion: 1,
    };
    const first = await encryptPayload(parameters);
    const second = await encryptPayload(parameters);

    assert.notEqual(first.nonce, second.nonce);
    assert.equal(await decryptPayload({
        envelope: first,
        key,
        conversationId: 42,
        clientMessageUuid,
        senderId: 9,
    }), parameters.plaintext);

    await assert.rejects(() => decryptPayload({
        envelope: first,
        key: wrongKey,
        conversationId: 42,
        clientMessageUuid,
        senderId: 9,
    }));
    await assert.rejects(() => decryptPayload({
        envelope: first,
        key,
        conversationId: 43,
        clientMessageUuid,
        senderId: 9,
    }));

    const tampered = { ...first, ciphertext: `${first.ciphertext.slice(0, -1)}${first.ciphertext.endsWith('A') ? 'B' : 'A'}` };
    await assert.rejects(() => decryptPayload({
        envelope: tampered,
        key,
        conversationId: 42,
        clientMessageUuid,
        senderId: 9,
    }));
});

test('encrypted edits retain stable AAD identity while replacing only ciphertext', async () => {
    const key = await generateConversationKey();
    const clientMessageUuid = await generateClientMessageUuid();
    const common = {
        key,
        conversationId: 55,
        clientMessageUuid,
        senderId: 4,
        keyVersion: 1,
    };
    const original = await encryptPayload({ ...common, plaintext: 'Original text' });
    const edited = await encryptPayload({ ...common, plaintext: 'Edited text' });

    assert.notEqual(original.ciphertext, edited.ciphertext);
    assert.equal(await decryptPayload({
        envelope: edited,
        key,
        conversationId: common.conversationId,
        clientMessageUuid,
        senderId: common.senderId,
    }), 'Edited text');
});

test('E2EE attachment bytes keys and private metadata roundtrip only in the browser', async () => {
    const conversationKey = await generateConversationKey();
    const wrongKey = await generateConversationKey();
    const plaintext = new TextEncoder().encode('private PDF bytes that must never reach Laravel');
    const common = {
        bytes: plaintext,
        name: 'private-report.pdf',
        mimeType: 'application/pdf',
        conversationKey,
        conversationId: 71,
        clientMessageUuid: await generateClientMessageUuid(),
        senderId: 8,
        keyVersion: 1,
    };
    const first = await encryptAttachment(common);
    const second = await encryptAttachment(common);
    const serverPayload = JSON.stringify({ ...first, ciphertext: '[binary ciphertext]' });

    assert.notDeepEqual(first.ciphertext, plaintext);
    assert.notEqual(first.client_attachment_uuid, second.client_attachment_uuid);
    assert.notEqual(first.encrypted_key, second.encrypted_key);
    assert.notDeepEqual(first.ciphertext.slice(0, 24), second.ciphertext.slice(0, 24));
    assert.equal(serverPayload.includes('private-report.pdf'), false);
    assert.equal(serverPayload.includes('application/pdf'), false);

    const decrypted = await decryptAttachment({
        ciphertext: first.ciphertext,
        encryptedKey: first.encrypted_key,
        encryptedMetadata: first.encrypted_metadata,
        conversationKey,
        conversationId: common.conversationId,
        clientMessageUuid: common.clientMessageUuid,
        clientAttachmentUuid: first.client_attachment_uuid,
        senderId: common.senderId,
        keyVersion: 1,
    });
    assert.deepEqual(decrypted.bytes, plaintext);
    assert.equal(decrypted.metadata.original_name, 'private-report.pdf');
    assert.equal(decrypted.metadata.mime_type, 'application/pdf');

    await assert.rejects(() => decryptAttachmentMetadata({
        encryptedKey: first.encrypted_key,
        encryptedMetadata: first.encrypted_metadata,
        conversationKey: wrongKey,
        conversationId: common.conversationId,
        clientMessageUuid: common.clientMessageUuid,
        clientAttachmentUuid: first.client_attachment_uuid,
        senderId: common.senderId,
        keyVersion: 1,
    }));
    await assert.rejects(() => encryptAttachment({ ...common, conversationKey: null }));
    const tampered = first.ciphertext.slice();
    tampered[tampered.length - 1] ^= 1;
    await assert.rejects(() => decryptAttachment({
        ciphertext: tampered,
        encryptedKey: first.encrypted_key,
        encryptedMetadata: first.encrypted_metadata,
        conversationKey,
        conversationId: common.conversationId,
        clientMessageUuid: common.clientMessageUuid,
        clientAttachmentUuid: first.client_attachment_uuid,
        senderId: common.senderId,
        keyVersion: 1,
    }));
    await assert.rejects(() => decryptAttachmentMetadata({
        encryptedKey: first.encrypted_key,
        encryptedMetadata: first.encrypted_metadata,
        conversationKey,
        conversationId: 999,
        clientMessageUuid: common.clientMessageUuid,
        clientAttachmentUuid: first.client_attachment_uuid,
        senderId: common.senderId,
        keyVersion: 1,
    }));
});

test('E2EE reaction values are opaque to Laravel and aggregate only after browser decryption', async () => {
    const key = await generateConversationKey();
    const common = { key, conversationId: 12, messageId: 44, keyVersion: 1 };
    const heart = await encryptReaction({ ...common, userId: 3, reaction: '❤️' });
    const thumb = await encryptReaction({ ...common, userId: 4, reaction: '👍' });
    const heartTwo = await encryptReaction({ ...common, userId: 5, reaction: '❤️' });

    assert.equal(JSON.stringify(heart).includes('❤️'), false);
    const decrypted = await Promise.all([
        [heart, 3], [thumb, 4], [heartTwo, 5],
    ].map(async ([payload, userId]) => ({
        user_id: userId,
        reaction: await decryptReaction({
            encryptedReaction: payload.encrypted_reaction,
            key,
            conversationId: common.conversationId,
            messageId: common.messageId,
            userId,
        }),
    })));
    const grouped = groupDecryptedReactions(decrypted);
    assert.equal(grouped.find(item => item.reaction === '❤️').count, 2);
    assert.equal(grouped.find(item => item.reaction === '👍').count, 1);

    const parsed = JSON.parse(heart.encrypted_reaction);
    parsed.ciphertext = `${parsed.ciphertext.slice(0, -1)}${parsed.ciphertext.endsWith('A') ? 'B' : 'A'}`;
    await assert.rejects(() => decryptReaction({
        encryptedReaction: parsed,
        key,
        conversationId: common.conversationId,
        messageId: common.messageId,
        userId: 3,
    }));
    const wrongReactionKey = await generateConversationKey();
    await assert.rejects(() => decryptReaction({
        encryptedReaction: heart.encrypted_reaction,
        key: wrongReactionKey,
        conversationId: common.conversationId,
        messageId: common.messageId,
        userId: 3,
    }));
    await assert.rejects(() => encryptReaction({ ...common, userId: 3, reaction: '❤️', key: null }));
});

test('trusted-device approval and rotation payloads contain only wrapped keys and signed public metadata', async () => {
    const sodium = await initializeCrypto();
    const deviceA = await createDeviceIdentity('Trusted A');
    const deviceB = await createDeviceIdentity('New B');
    const deviceC = await createDeviceIdentity('Peer C');
    const revoked = await createDeviceIdentity('Revoked');
    const currentKey = await generateConversationKey();
    const wrappedForA = await wrapConversationKeyForDevice(currentKey, deviceA.publicEncryptionKey);
    const openedByA = await unwrapConversationKey(wrappedForA, deviceA.publicEncryptionKey, deviceA.privateEncryptionKey);
    const approvalProvisioning = [{
        conversation_id: 91,
        key_version: 1,
        wrapped_key: await wrapConversationKeyForDevice(openedByA, deviceB.publicEncryptionKey),
        wrapping_algorithm: 'x25519-sealedbox',
        format_version: 1,
    }];
    const approvalCanonical = deviceApprovalCanonical({
        userId: 7,
        approverDeviceId: 1,
        targetDeviceId: 2,
        challenge: 'signed-server-challenge',
        provisioning: approvalProvisioning,
    });
    const approvalSignature = await signDeviceProof(approvalCanonical, deviceA.privateSigningKey);
    const restorationCanonical = deviceRestorationCanonical({
        userId: 7,
        approverDeviceId: 1,
        targetDeviceId: 2,
        challenge: 'restore-server-challenge',
        provisioning: approvalProvisioning,
    });
    const restorationSignature = await signDeviceProof(restorationCanonical, deviceA.privateSigningKey);

    assert.equal(approvalCanonical.includes(Buffer.from(currentKey).toString('base64')), false);
    assert.equal(approvalCanonical.includes(deviceA.privateEncryptionKey), false);
    assert.equal(approvalCanonical.includes(deviceA.privateSigningKey), false);
    assert.equal(sodium.crypto_sign_verify_detached(
        sodium.from_base64(approvalSignature, sodium.base64_variants.URLSAFE_NO_PADDING),
        sodium.from_string(approvalCanonical),
        sodium.from_base64(deviceA.publicSigningKey, sodium.base64_variants.URLSAFE_NO_PADDING),
    ), true);
    assert.deepEqual(await unwrapConversationKey(
        approvalProvisioning[0].wrapped_key,
        deviceB.publicEncryptionKey,
        deviceB.privateEncryptionKey,
    ), currentKey);
    assert.match(restorationCanonical, /"purpose":"device_restoration"/);
    assert.equal(sodium.crypto_sign_verify_detached(
        sodium.from_base64(restorationSignature, sodium.base64_variants.URLSAFE_NO_PADDING),
        sodium.from_string(restorationCanonical),
        sodium.from_base64(deviceA.publicSigningKey, sodium.base64_variants.URLSAFE_NO_PADDING),
    ), true);

    const nextKey = await generateConversationKey();
    assert.notDeepEqual(nextKey, currentKey);
    const rotationKeys = await Promise.all([deviceA, deviceB, deviceC].map(async (device, index) => ({
        device_id: index + 1,
        wrapped_key: await wrapConversationKeyForDevice(nextKey, device.publicEncryptionKey),
        wrapping_algorithm: 'x25519-sealedbox',
        format_version: 1,
    })));
    const rotationCanonical = conversationRotationCanonical({
        userId: 7,
        deviceId: 1,
        conversationId: 91,
        keyVersion: 2,
        wrappedKeys: rotationKeys,
    });
    const rotationSignature = await signDeviceProof(rotationCanonical, deviceA.privateSigningKey);
    assert.equal(rotationCanonical.includes(revoked.publicEncryptionKey), false);
    assert.equal(sodium.crypto_sign_verify_detached(
        sodium.from_base64(rotationSignature, sodium.base64_variants.URLSAFE_NO_PADDING),
        sodium.from_string(rotationCanonical),
        sodium.from_base64(deviceA.publicSigningKey, sodium.base64_variants.URLSAFE_NO_PADDING),
    ), true);
    assert.deepEqual(await unwrapConversationKey(
        rotationKeys[2].wrapped_key,
        deviceC.publicEncryptionKey,
        deviceC.privateEncryptionKey,
    ), nextKey);
    await assert.rejects(() => unwrapConversationKey(
        rotationKeys[0].wrapped_key,
        revoked.publicEncryptionKey,
        revoked.privateEncryptionKey,
    ));
});

test('Secure Devices control opens, requests the own-device list, and exposes loading failures', async () => {
    const requested = [];
    globalThis.fetch = async url => {
        requested.push(url);
        return {
            ok: true,
            json: async () => ({ devices: [{ id: 9, device_uuid: 'device-browser' }] }),
        };
    };

    const manager = createE2eeDeviceManager({
        registerDeviceUrl: '/team-chat/e2ee/devices',
        devicesIndexUrl: '/team-chat/e2ee/devices',
    }, {
        registerCurrentDevice: async () => ({
            id: 9,
            deviceUuid: 'device-browser',
            trusted_at: '2026-09-03T00:00:00Z',
            revoked_at: null,
        }),
    });
    await manager.show();

    assert.equal(manager.open, true);
    assert.equal(manager.loading, false);
    assert.equal(manager.error, '');
    assert.equal(manager.devices.length, 1);
    assert.deepEqual(requested, ['/team-chat/e2ee/devices']);

    globalThis.fetch = async () => ({
        ok: false,
        json: async () => ({ message: 'Device directory unavailable.' }),
    });
    await manager.refresh();

    assert.equal(manager.loading, false);
    assert.equal(manager.error, 'Device directory unavailable.');
});

test('Secure Devices allows only a trusted current device to approve another active device', async () => {
    const trusted = {
        id: 1,
        device_uuid: 'trusted-browser',
        trusted_at: '2026-09-03T00:00:00Z',
        revoked_at: null,
    };
    const awaiting = {
        id: 2,
        device_uuid: 'awaiting-browser',
        trusted_at: null,
        revoked_at: null,
    };
    globalThis.fetch = async () => ({
        ok: true,
        json: async () => ({ devices: [trusted, awaiting] }),
    });

    const untrustedManager = createE2eeDeviceManager({
        registerDeviceUrl: '/team-chat/e2ee/devices',
        devicesIndexUrl: '/team-chat/e2ee/devices',
    }, {
        registerCurrentDevice: async () => ({ ...awaiting, deviceUuid: awaiting.device_uuid }),
    });
    await untrustedManager.show();

    assert.equal(untrustedManager.currentDeviceAwaitingApproval(), true);
    assert.equal(untrustedManager.hasTrustedApprover(), true);
    assert.equal(untrustedManager.canApprove(awaiting), false);
    assert.equal(untrustedManager.canApprove(trusted), false);

    const trustedManager = createE2eeDeviceManager({
        registerDeviceUrl: '/team-chat/e2ee/devices',
        devicesIndexUrl: '/team-chat/e2ee/devices',
    }, {
        registerCurrentDevice: async () => ({ ...trusted, deviceUuid: trusted.device_uuid }),
    });
    await trustedManager.show();

    assert.equal(trustedManager.currentDeviceAwaitingApproval(), false);
    assert.equal(trustedManager.canApprove(awaiting), true);
    assert.equal(trustedManager.canApprove(trusted), false);
});

test('E2EE runtime exports both Alpine control handlers without starting a second Alpine instance', () => {
    const messenger = createTeamE2eeMessenger({
        currentUserId: 1,
        details: { id: 2, type: 'internal_direct', is_e2ee: false },
        messages: [],
    });

    assert.equal(typeof messenger.enable, 'function');
    assert.equal(typeof managerFactory(), 'object');
    assert.equal(window.BrahmaE2eeTeam, createTeamE2eeMessenger);
    assert.equal(window.BrahmaE2eeDevices, createE2eeDeviceManager);
    assert.equal(Object.hasOwn(window, 'Alpine'), false);
});

test('re-enabling E2EE advances the key version and generates independent browser key material', async () => {
    assert.equal(nextActivationKeyVersion(null), 1);
    assert.equal(nextActivationKeyVersion(1), 2);
    assert.equal(nextActivationKeyVersion(7), 8);

    const oldKey = await generateConversationKey();
    const reenabledKey = await generateConversationKey();
    assert.notDeepEqual(reenabledKey, oldKey);
});

function managerFactory() {
    return createE2eeDeviceManager({ devicesIndexUrl: '/team-chat/e2ee/devices' });
}
