import {
    decryptAttachment,
    decryptAttachmentMetadata,
    decryptPayload,
    decryptReaction,
    deviceApprovalCanonical,
    E2EE_ATTACHMENT_PLAINTEXT_MAX_BYTES,
    encryptAttachment,
    encryptPayload,
    encryptReaction,
    encryptedMessageRequest,
    ensureDeviceIdentity,
    generateConversationKey,
    generateClientMessageUuid,
    groupDecryptedReactions,
    loadDeviceKeyMaterial,
    conversationRotationCanonical,
    signDeviceProof,
    unwrapConversationKey,
    wrapConversationKeyForDevice,
} from './index.js';

function csrfToken() {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!token) throw new Error('The secure request token is unavailable.');
    return token;
}

async function request(url, options = {}) {
    const isFormData = options.body instanceof FormData;
    const method = String(options.method || 'GET').toUpperCase();
    const needsCsrf = !['GET', 'HEAD', 'OPTIONS'].includes(method);
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(needsCsrf ? { 'X-CSRF-TOKEN': csrfToken() } : {}),
            ...(!isFormData ? { 'Content-Type': 'application/json' } : {}),
            ...options.headers,
        },
        ...options,
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new Error(payload.message || 'The encrypted operation could not be completed.');
    }
    return payload;
}

const deviceRegistrations = new Map();

async function registerCurrentDevice(registerDeviceUrl, currentUserId, devicesIndexUrl) {
    const registrationKey = `${registerDeviceUrl}:${currentUserId}`;

    if (!deviceRegistrations.has(registrationKey)) {
        const registration = (async () => {
            let publicBundle;
            try {
                const directory = await request(devicesIndexUrl);
                publicBundle = await ensureDeviceIdentity(
                    currentUserId,
                    directory.devices || [],
                );
            } catch {
                throw new Error('Unable to initialize secure messaging on this browser.');
            }
            const response = await request(registerDeviceUrl, {
                method: 'POST',
                body: JSON.stringify(publicBundle),
            });

            return { ...response.device, deviceUuid: publicBundle.device_uuid };
        })().catch(error => {
            deviceRegistrations.delete(registrationKey);
            throw error;
        });

        deviceRegistrations.set(registrationKey, registration);
    }

    return deviceRegistrations.get(registrationKey);
}

function rememberCurrentDevice(registerDeviceUrl, currentUserId, device, deviceUuid) {
    const registration = { ...device, deviceUuid };
    deviceRegistrations.set(`${registerDeviceUrl}:${currentUserId}`, Promise.resolve(registration));

    return registration;
}

export function nextActivationKeyVersion(currentKeyVersion) {
    return Number(currentKeyVersion || 0) + 1;
}

export function createTeamE2eeMessenger(config) {
    const conversationKeys = new Map();
    let deviceRegistration = null;
    const decryptedSignatures = new Map();
    const reactionSignatures = new Map();
    let activeConversationId = config.details.id;

    return {
        draft: '',
        editText: '',
        editingId: null,
        replyToId: null,
        decrypted: {},
        selectedFile: null,
        attachmentViews: {},
        reactionSummaries: {},
        busy: false,
        securityMessage: '',
        details: config.details,
        messages: config.messages,

        async init() {
            if (this.details.type !== 'internal_direct') return;
            try {
                await this.ensureRegisteredDevice();
                if (this.details.is_e2ee || this.hasEncryptedHistory()) {
                    await this.loadConversationKeyAndDecrypt();
                    if (this.details.e2ee_rotation_required) {
                        this.securityMessage = 'Encryption key update required before sending new messages.';
                    }
                }
            } catch (error) {
                this.securityMessage = this.details.is_e2ee
                    ? this.deviceAccessMessage()
                    : 'Secure-device setup is currently unavailable.';
            }
        },

        async sync(event) {
            const detail = event?.detail || event;
            if (!detail?.details || detail.details.id !== this.details.id) return;
            if (activeConversationId !== detail.details.id) {
                this.revokeAttachmentUrls();
                activeConversationId = detail.details.id;
            }
            this.details = detail.details;
            this.messages = detail.messages || [];
            if (this.details.is_e2ee || this.hasEncryptedHistory()) {
                try {
                    await this.loadConversationKey();
                    await this.decryptMessages();
                    if (this.details.e2ee_rotation_required) {
                        this.securityMessage = 'Encryption key update required before sending new messages.';
                    }
                } catch {
                    this.securityMessage = this.deviceAccessMessage();
                }
            }
        },

        async deviceStatusChanged() {
            deviceRegistration = null;
            conversationKeys.clear();
            if (!this.details.is_e2ee) return;
            try {
                await this.loadConversationKeyAndDecrypt();
                this.securityMessage = this.details.e2ee_rotation_required
                    ? 'Encryption key update required before sending new messages.'
                    : '';
            } catch {
                this.securityMessage = this.deviceAccessMessage();
            }
        },

        deviceAccessMessage() {
            if (deviceRegistration?.revoked_at) return 'This secure device has been revoked.';
            if (!deviceRegistration?.trusted_at) return 'This device needs approval before it can read encrypted messages.';
            return 'This device has not been given access to this encrypted conversation.';
        },

        hasEncryptedHistory() {
            return this.messages.some(message => message.is_encrypted
                || message.reply?.is_encrypted
                || message.attachments?.some(attachment => attachment.is_encrypted));
        },

        async ensureRegisteredDevice() {
            if (deviceRegistration) return deviceRegistration;
            deviceRegistration = await registerCurrentDevice(
                config.registerDeviceUrl,
                config.currentUserId,
                config.devicesIndexUrl,
            );
            return deviceRegistration;
        },

        async enable() {
            if (this.busy || this.details.is_e2ee) return;
            this.busy = true;
            this.securityMessage = 'Initializing secure messaging…';
            try {
                const currentDevice = await this.ensureRegisteredDevice();
                if (!currentDevice.trusted_at) {
                    throw new Error('This device needs approval before it can enable end-to-end encryption.');
                }
                const directory = await request(config.devicesUrl);
                const participantIds = [...new Set((this.details.members || []).map(member => member.id))];
                const provisionedUsers = [...new Set(directory.devices.map(device => device.user_id))];
                if (participantIds.length !== 2 || participantIds.some(id => !provisionedUsers.includes(id))) {
                    throw new Error('End-to-end encryption is not available yet because the other participant has not completed secure-device setup.');
                }

                const keyVersion = nextActivationKeyVersion(this.details.current_key_version);
                const generatedKey = await generateConversationKey();
                const wrappedKeys = await Promise.all(directory.devices.map(async device => ({
                    device_id: device.id,
                    wrapped_key: await wrapConversationKeyForDevice(generatedKey, device.public_encryption_key),
                    wrapping_algorithm: 'x25519-sealedbox',
                    format_version: 1,
                })));
                await request(config.activateUrl, {
                    method: 'POST',
                    body: JSON.stringify({ key_version: keyVersion, wrapped_keys: wrappedKeys }),
                });

                conversationKeys.clear();
                await this.$wire.refreshTeam();
                await this.loadConversationKeyAndDecrypt();
                this.securityMessage = 'End-to-end encryption is enabled for new messages.';
            } catch (error) {
                this.securityMessage = error.message || 'Unable to enable end-to-end encryption.';
            } finally {
                this.busy = false;
            }
        },

        async rotate() {
            if (this.busy || !this.details.is_e2ee || !this.details.e2ee_rotation_required) return;
            this.busy = true;
            this.securityMessage = 'Updating encryption keys…';
            try {
                const currentDevice = await this.ensureRegisteredDevice();
                if (!currentDevice.trusted_at || currentDevice.revoked_at) {
                    throw new Error('A trusted active device is required to update encryption.');
                }
                await this.loadConversationKey(this.details.current_key_version);
                const directory = await request(config.devicesUrl);
                const participantIds = [...new Set((this.details.members || []).map(member => member.id))];
                const provisionedUsers = [...new Set(directory.devices.map(device => device.user_id))];
                if (participantIds.length !== 2 || participantIds.some(id => !provisionedUsers.includes(id))) {
                    throw new Error('Each participant needs an active trusted device before encryption can be updated.');
                }

                const nextVersion = Number(this.details.current_key_version) + 1;
                const nextKey = await generateConversationKey();
                const wrappedKeys = await Promise.all(directory.devices.map(async device => ({
                    device_id: device.id,
                    wrapped_key: await wrapConversationKeyForDevice(nextKey, device.public_encryption_key),
                    wrapping_algorithm: 'x25519-sealedbox',
                    format_version: 1,
                })));
                const localKeys = await loadDeviceKeyMaterial(currentDevice.deviceUuid);
                const canonical = conversationRotationCanonical({
                    userId: config.currentUserId,
                    deviceId: currentDevice.id,
                    conversationId: this.details.id,
                    keyVersion: nextVersion,
                    wrappedKeys,
                });
                const signature = await signDeviceProof(canonical, localKeys.privateSigningKey);
                await request(config.rotateUrl, {
                    method: 'POST',
                    body: JSON.stringify({
                        initiator_device_uuid: currentDevice.deviceUuid,
                        key_version: nextVersion,
                        wrapped_keys: wrappedKeys,
                        signature,
                    }),
                });
                conversationKeys.set(nextVersion, nextKey);
                await this.$wire.refreshTeam();
                this.securityMessage = 'Encryption keys were updated safely.';
            } catch (error) {
                await this.$wire.refreshTeam();
                this.securityMessage = error.message || 'Unable to update encryption keys.';
            } finally {
                this.busy = false;
            }
        },

        async loadConversationKey(keyVersion = this.details.current_key_version) {
            keyVersion = Number(keyVersion);
            if (conversationKeys.has(keyVersion)) return conversationKeys.get(keyVersion);
            const registered = await this.ensureRegisteredDevice();
            const query = new URLSearchParams({ device_uuid: registered.deviceUuid, key_version: keyVersion });
            const response = await request(`${config.wrappedKeyUrl}?${query}`);
            const localKeys = await loadDeviceKeyMaterial(registered.deviceUuid);
            const conversationKey = await unwrapConversationKey(
                response.key.wrapped_key,
                localKeys.publicEncryptionKey,
                localKeys.privateEncryptionKey,
            );
            conversationKeys.set(keyVersion, conversationKey);
            return conversationKey;
        },

        async loadConversationKeyAndDecrypt() {
            await this.loadConversationKey();
            await this.decryptMessages();
        },

        async decryptMessages() {
            await this.loadConversationKey();
            const visibleAttachmentIds = new Set(this.messages.flatMap(message => (message.attachments || []).map(attachment => String(attachment.id))));
            Object.entries(this.attachmentViews).forEach(([id, view]) => {
                if (!visibleAttachmentIds.has(String(id))) {
                    if (view?.url) URL.revokeObjectURL(view.url);
                    delete this.attachmentViews[id];
                }
            });
            for (const message of this.messages) {
                if (message.deleted) {
                    delete this.decrypted[message.id];
                    delete this.reactionSummaries[message.id];
                    continue;
                }
                if (message.is_encrypted) await this.decryptOne(message);
                if (message.attachments?.length) await this.decryptAttachmentMetadataFor(message);
                if (message.reply?.is_encrypted && !message.reply.deleted) await this.decryptOne(message.reply);
                if (message.reply?.attachments?.length) await this.decryptAttachmentMetadataFor(message.reply);
                if (message.is_encrypted) await this.decryptReactionsFor(message);
            }
        },

        async decryptOne(message) {
            const signature = `${message.edited_at || ''}:${message.encrypted_payload}`;
            if (decryptedSignatures.get(message.id) === signature) return;
            try {
                this.decrypted[message.id] = await decryptPayload({
                    envelope: JSON.parse(message.encrypted_payload),
                    key: await this.loadConversationKey(message.key_version),
                    conversationId: this.details.id,
                    clientMessageUuid: message.client_message_uuid,
                    senderId: message.sender_id,
                });
            } catch {
                this.decrypted[message.id] = 'Unable to decrypt this message on this device.';
            }
            decryptedSignatures.set(message.id, signature);
        },

        async decryptAttachmentMetadataFor(message) {
            for (const attachment of message.attachments || []) {
                if (!attachment.is_encrypted || this.attachmentViews[attachment.id]?.metadata) continue;
                try {
                    const decryptedAttachment = await decryptAttachmentMetadata({
                        encryptedKey: attachment.encrypted_key,
                        encryptedMetadata: attachment.encrypted_metadata,
                        conversationKey: await this.loadConversationKey(attachment.key_version),
                        conversationId: this.details.id,
                        clientMessageUuid: message.client_message_uuid,
                        clientAttachmentUuid: attachment.client_attachment_uuid,
                        senderId: message.sender_id,
                        keyVersion: attachment.key_version,
                    });
                    this.attachmentViews[attachment.id] = { metadata: decryptedAttachment.metadata, url: null, loading: false };
                } catch {
                    this.attachmentViews[attachment.id] = { metadata: null, url: null, loading: false, error: true };
                }
            }
        },

        async loadAttachment(message, attachment) {
            if (this.attachmentViews[attachment.id]?.loading || this.attachmentViews[attachment.id]?.url) return;
            this.attachmentViews[attachment.id] = { ...(this.attachmentViews[attachment.id] || {}), loading: true };
            try {
                const response = await fetch(attachment.view_url, { credentials: 'same-origin', headers: { Accept: 'application/octet-stream' } });
                if (!response.ok) throw new Error('The encrypted attachment is unavailable.');
                const decryptedAttachment = await decryptAttachment({
                    ciphertext: new Uint8Array(await response.arrayBuffer()),
                    encryptedKey: attachment.encrypted_key,
                    encryptedMetadata: attachment.encrypted_metadata,
                    conversationKey: await this.loadConversationKey(attachment.key_version),
                    conversationId: this.details.id,
                    clientMessageUuid: message.client_message_uuid,
                    clientAttachmentUuid: attachment.client_attachment_uuid,
                    senderId: message.sender_id,
                    keyVersion: attachment.key_version,
                });
                const blob = new Blob([decryptedAttachment.bytes], { type: decryptedAttachment.metadata.mime_type });
                this.attachmentViews[attachment.id] = {
                    metadata: decryptedAttachment.metadata,
                    url: URL.createObjectURL(blob),
                    loading: false,
                };
            } catch {
                this.attachmentViews[attachment.id] = { metadata: null, url: null, loading: false, error: true };
                this.securityMessage = 'Unable to decrypt this attachment on this device.';
            }
        },

        attachmentView(id) {
            return this.attachmentViews[id] || {};
        },

        async downloadAttachment(message, attachment) {
            await this.loadAttachment(message, attachment);
            const view = this.attachmentView(attachment.id);
            if (!view.url || !view.metadata) return;
            const link = document.createElement('a');
            link.href = view.url;
            link.download = view.metadata.original_name;
            link.click();
        },

        revokeAttachmentUrls() {
            Object.values(this.attachmentViews).forEach(view => {
                if (view?.url) URL.revokeObjectURL(view.url);
            });
            this.attachmentViews = {};
        },

        destroy() {
            this.revokeAttachmentUrls();
        },

        async decryptReactionsFor(message) {
            const signature = JSON.stringify((message.reactions || []).map(reaction => [reaction.id, reaction.encrypted_reaction]));
            if (reactionSignatures.get(message.id) === signature) return;
            const decryptedReactions = [];
            for (const reaction of message.reactions || []) {
                if (!reaction.encrypted_reaction) continue;
                try {
                    decryptedReactions.push({
                        user_id: reaction.user_id,
                        reaction: await decryptReaction({
                            encryptedReaction: reaction.encrypted_reaction,
                            key: await this.loadConversationKey(reaction.key_version),
                            conversationId: this.details.id,
                            messageId: message.id,
                            userId: reaction.user_id,
                        }),
                    });
                } catch {
                    // A malformed reaction is isolated from the rest of the conversation.
                }
            }
            this.reactionSummaries[message.id] = groupDecryptedReactions(decryptedReactions);
            reactionSignatures.set(message.id, signature);
        },

        reactionsFor(message) {
            return message.is_encrypted ? (this.reactionSummaries[message.id] || []) : message.reactions;
        },

        textFor(message) {
            return message.deleted
                ? 'This message was deleted.'
                : (message.is_encrypted ? (this.decrypted[message.id] || 'Decrypting encrypted message…') : message.body);
        },

        replyText(reply) {
            if (!reply) return '';
            if (reply.deleted) return 'This message was deleted.';
            if (reply.is_encrypted) {
                const text = this.decrypted[reply.id];
                if (text) return text;
                const attachment = reply.attachments?.[0];
                const metadata = attachment ? this.attachmentView(attachment.id).metadata : null;
                return metadata?.original_name || (attachment ? 'Encrypted attachment' : 'Encrypted message');
            }
            return reply.body || 'Attachment';
        },

        chooseAttachment(event) {
            const file = event.target.files?.[0] || null;
            this.securityMessage = '';
            if (!file) {
                this.selectedFile = null;
                return;
            }
            if (file.size > E2EE_ATTACHMENT_PLAINTEXT_MAX_BYTES) {
                event.target.value = '';
                this.selectedFile = null;
                this.securityMessage = 'Encrypted attachments may not exceed 1,750 KiB.';
                return;
            }
            this.selectedFile = file;
        },

        clearAttachment() {
            this.selectedFile = null;
            if (this.$refs.encryptedAttachment) this.$refs.encryptedAttachment.value = '';
        },

        setReply(message) {
            if (!this.details.is_e2ee || message.deleted) return;
            this.replyToId = message.id;
        },

        messageById(id) {
            return this.messages.find(message => message.id === id) || null;
        },

        beginEdit(message) {
            if (!this.details.is_e2ee || message.deleted || message.sender_id !== config.currentUserId) return;
            this.editingId = message.id;
            this.editText = this.decrypted[message.id] || '';
        },

        cancelEdit() {
            this.editingId = null;
            this.editText = '';
        },

        async send() {
            const plaintext = this.draft.trim();
            if ((!plaintext && !this.selectedFile) || this.busy || this.details.e2ee_rotation_required) return;
            this.busy = true;
            this.securityMessage = '';
            try {
                const conversationKey = await this.loadConversationKey();
                const clientMessageUuid = await generateClientMessageUuid();
                const payload = await encryptedMessageRequest({
                    plaintext,
                    key: conversationKey,
                    conversationId: this.details.id,
                    senderId: config.currentUserId,
                    keyVersion: this.details.current_key_version,
                    replyToMessageId: this.replyToId,
                    clientMessageUuid,
                });
                if (this.selectedFile) {
                    const attachment = await encryptAttachment({
                        bytes: new Uint8Array(await this.selectedFile.arrayBuffer()),
                        name: this.selectedFile.name,
                        mimeType: this.selectedFile.type,
                        conversationKey,
                        conversationId: this.details.id,
                        clientMessageUuid,
                        senderId: config.currentUserId,
                        keyVersion: this.details.current_key_version,
                    });
                    const form = new FormData();
                    form.append('ciphertext', new Blob([attachment.ciphertext], { type: 'application/octet-stream' }), 'ciphertext.bin');
                    Object.entries({ ...payload, ...attachment }).forEach(([key, value]) => {
                        if (key !== 'ciphertext' && value !== null && value !== undefined) form.append(key, String(value));
                    });
                    await request(config.attachmentsUrl, { method: 'POST', body: form });
                } else {
                    await request(config.messagesUrl, {
                        method: 'POST',
                        body: JSON.stringify(payload),
                    });
                }
                this.draft = '';
                this.replyToId = null;
                this.clearAttachment();
                await this.$wire.refreshTeam();
                window.dispatchEvent(new CustomEvent('team-messenger-scroll', { detail: { force: true } }));
            } catch {
                this.securityMessage = 'Unable to send this encrypted message.';
            } finally {
                this.busy = false;
            }
        },

        async react(message, reaction) {
            if (this.busy || message.deleted || this.details.e2ee_rotation_required) return;
            this.busy = true;
            this.securityMessage = '';
            try {
                const own = (this.reactionSummaries[message.id] || []).find(summary => summary.user_ids.includes(config.currentUserId));
                const url = config.reactionUrl.replace('__MESSAGE__', message.id);
                if (own?.reaction === reaction) {
                    await request(url, { method: 'DELETE' });
                } else {
                    const payload = await encryptReaction({
                        reaction,
                        key: await this.loadConversationKey(),
                        conversationId: this.details.id,
                        messageId: message.id,
                        userId: config.currentUserId,
                        keyVersion: this.details.current_key_version,
                    });
                    await request(url, { method: 'PUT', body: JSON.stringify(payload) });
                }
                reactionSignatures.delete(message.id);
                await this.$wire.refreshTeam();
            } catch {
                this.securityMessage = 'Unable to update this encrypted reaction.';
            } finally {
                this.busy = false;
            }
        },

        async saveEdit(message) {
            const plaintext = this.editText.trim();
            if (!plaintext || this.busy || this.details.e2ee_rotation_required) return;
            this.busy = true;
            try {
                const conversationKey = await this.loadConversationKey();
                const envelope = await encryptPayload({
                    plaintext,
                    key: conversationKey,
                    conversationId: this.details.id,
                    clientMessageUuid: message.client_message_uuid,
                    senderId: config.currentUserId,
                    keyVersion: this.details.current_key_version,
                });
                await request(config.editMessageUrl.replace('__MESSAGE__', message.id), {
                    method: 'PATCH',
                    body: JSON.stringify({
                        encrypted_payload: JSON.stringify(envelope),
                        encryption_version: envelope.v,
                        key_version: envelope.key_version,
                    }),
                });
                this.cancelEdit();
                await this.$wire.refreshTeam();
            } catch {
                this.securityMessage = 'Unable to edit this encrypted message.';
            } finally {
                this.busy = false;
            }
        },
    };
}

export function createE2eeDeviceManager(config, dependencies = {}) {
    let currentRegistration = null;
    const registerDevice = dependencies.registerCurrentDevice || registerCurrentDevice;

    return {
        open: false,
        busy: false,
        loading: false,
        error: '',
        devices: [],
        currentDeviceUuid: null,

        async init() {
            this.error = '';
            try {
                currentRegistration = await registerDevice(
                    config.registerDeviceUrl,
                    config.currentUserId,
                    config.devicesIndexUrl,
                );
                this.currentDeviceUuid = currentRegistration.deviceUuid;
            } catch (error) {
                this.error = error.message || 'Unable to initialize secure messaging on this browser.';
            }
        },

        async show() {
            this.open = true;
            if (!this.currentDeviceUuid) {
                await this.init();
            }
            if (!this.currentDeviceUuid) return;
            await this.refresh();
        },

        async refresh() {
            this.loading = true;
            this.error = '';
            try {
                const response = await request(config.devicesIndexUrl);
                this.devices = response.devices || [];
                const refreshedCurrent = this.devices.find(device => device.device_uuid === this.currentDeviceUuid);
                if (refreshedCurrent && currentRegistration) {
                    currentRegistration = rememberCurrentDevice(
                        config.registerDeviceUrl,
                        config.currentUserId,
                        refreshedCurrent,
                        this.currentDeviceUuid,
                    );
                }
            } catch (error) {
                this.error = error.message || 'Unable to load secure devices.';
            } finally {
                this.loading = false;
            }
        },

        isCurrent(device) {
            return device.device_uuid === this.currentDeviceUuid;
        },

        currentDevice() {
            return this.devices.find(device => this.isCurrent(device)) || null;
        },

        currentDeviceAwaitingApproval() {
            const device = this.currentDevice();

            return Boolean(device && !device.trusted_at && !device.revoked_at);
        },

        hasTrustedApprover() {
            return this.devices.some(device => !this.isCurrent(device)
                && device.trusted_at && !device.revoked_at);
        },

        status(device) {
            if (device.revoked_at) return 'Revoked';
            return device.trusted_at ? 'Trusted' : 'Approval required';
        },

        canApprove(device) {
            return Boolean(!device.trusted_at && !device.revoked_at
                && currentRegistration?.trusted_at && !currentRegistration?.revoked_at
                && !this.isCurrent(device));
        },

        fingerprint(value) {
            return String(value || '').match(/.{1,4}/g)?.join(' ') || '';
        },

        date(value) {
            return value ? new Date(value).toLocaleString() : 'Never';
        },

        async approve(device) {
            if (!this.canApprove(device) || this.busy) return;
            if (!window.confirm(`Approve this secure device?\n\nFingerprint: ${this.fingerprint(device.key_fingerprint)}`)) return;
            this.busy = true;
            this.error = '';
            try {
                const planUrl = config.approvalPlanUrl.replace('__DEVICE__', device.id);
                const query = new URLSearchParams({ approver_device_uuid: this.currentDeviceUuid });
                const plan = await request(`${planUrl}?${query}`);
                const localKeys = await loadDeviceKeyMaterial(this.currentDeviceUuid);
                const provisioning = [];
                for (const conversation of plan.conversations) {
                    const key = await unwrapConversationKey(
                        conversation.approver_wrapped_key,
                        localKeys.publicEncryptionKey,
                        localKeys.privateEncryptionKey,
                    );
                    provisioning.push({
                        conversation_id: conversation.conversation_id,
                        key_version: conversation.key_version,
                        wrapped_key: await wrapConversationKeyForDevice(key, plan.target_device.public_encryption_key),
                        wrapping_algorithm: 'x25519-sealedbox',
                        format_version: 1,
                    });
                }
                const canonical = deviceApprovalCanonical({
                    userId: config.currentUserId,
                    approverDeviceId: plan.approver_device.id,
                    targetDeviceId: plan.target_device.id,
                    challenge: plan.challenge,
                    provisioning,
                });
                const signature = await signDeviceProof(canonical, localKeys.privateSigningKey);
                await request(config.approveUrl.replace('__DEVICE__', device.id), {
                    method: 'POST',
                    body: JSON.stringify({
                        approver_device_uuid: this.currentDeviceUuid,
                        challenge: plan.challenge,
                        provisioning,
                        signature,
                    }),
                });
                await this.refresh();
                window.dispatchEvent(new CustomEvent('e2ee-device-status-changed'));
            } catch (error) {
                this.error = error.message || 'Unable to approve this secure device.';
            } finally {
                this.busy = false;
            }
        },

        async revoke(device) {
            if (device.revoked_at || this.busy) return;
            const warning = 'Remove this secure device? It will stop receiving new keys. Encrypted data already downloaded to it cannot be remotely erased.';
            if (!window.confirm(warning)) return;
            this.busy = true;
            this.error = '';
            try {
                await request(config.revokeUrl.replace('__DEVICE__', device.id), { method: 'DELETE' });
                await this.refresh();
                await this.$wire.refreshTeam();
                window.dispatchEvent(new CustomEvent('e2ee-device-status-changed'));
            } catch (error) {
                this.error = error.message || 'Unable to revoke this secure device.';
            } finally {
                this.busy = false;
            }
        },
    };
}

window.BrahmaE2eeTeam = createTeamE2eeMessenger;
window.BrahmaE2eeDevices = createE2eeDeviceManager;
