import { decryptPayload, encryptPayload } from './conversation-crypto.js';

export function reactionAssociatedData({ conversationId, messageId, userId, keyVersion }) {
    if (!conversationId || !messageId || !userId || !Number.isInteger(keyVersion) || keyVersion < 1) {
        throw new Error('Complete E2EE reaction associated-data fields are required.');
    }

    return `brahmabull:e2ee:reaction:v1|conversation:${conversationId}|message:${messageId}|user:${userId}|key_version:${keyVersion}`;
}

function reactionIdentity(messageId, userId) {
    return `reaction-${messageId}-${userId}`;
}

export async function encryptReaction({ reaction, key, conversationId, messageId, userId, keyVersion }) {
    const allowed = ['👍', '❤️', '😂', '😮', '😢', '🙏'];
    if (!allowed.includes(reaction)) throw new Error('Unsupported encrypted reaction.');
    const clientMessageUuid = reactionIdentity(messageId, userId);
    const envelope = await encryptPayload({
        plaintext: JSON.stringify({ reaction }),
        key,
        conversationId,
        clientMessageUuid,
        senderId: userId,
        keyVersion,
        associatedDataOverride: reactionAssociatedData({ conversationId, messageId, userId, keyVersion }),
    });

    return { encrypted_reaction: JSON.stringify(envelope), encryption_version: envelope.v, key_version: keyVersion };
}

export async function decryptReaction({ encryptedReaction, key, conversationId, messageId, userId }) {
    const envelope = typeof encryptedReaction === 'string' ? JSON.parse(encryptedReaction) : encryptedReaction;
    const plaintext = await decryptPayload({
        envelope,
        key,
        conversationId,
        clientMessageUuid: reactionIdentity(messageId, userId),
        senderId: userId,
        associatedDataOverride: reactionAssociatedData({ conversationId, messageId, userId, keyVersion: envelope.key_version }),
    });
    const payload = JSON.parse(plaintext);
    if (!['👍', '❤️', '😂', '😮', '😢', '🙏'].includes(payload.reaction)) throw new Error('Unsupported decrypted reaction.');

    return payload.reaction;
}

export function groupDecryptedReactions(reactions) {
    const grouped = new Map();
    for (const { reaction, user_id: userId } of reactions) {
        if (!reaction) continue;
        const summary = grouped.get(reaction) || { reaction, count: 0, user_ids: [] };
        summary.count += 1;
        summary.user_ids.push(userId);
        grouped.set(reaction, summary);
    }

    return [...grouped.values()];
}
