import { concatBytes, fromBase64Url, toBase64Url } from './encoding.js';
import { initializeCrypto } from './sodium.js';

const DATABASE_NAME = 'brahmabull-e2ee-v1';
const STORE_NAME = 'device-identities';

export async function createDeviceIdentity(deviceName = null, ownerUserId = null) {
    const sodium = await initializeCrypto();
    const encryption = sodium.crypto_box_keypair();
    const signing = sodium.crypto_sign_keypair();
    const fingerprint = sodium.crypto_generichash(
        32,
        concatBytes(encryption.publicKey, signing.publicKey),
    );

    return {
        formatVersion: 1,
        ownerUserId: ownerUserId === null ? null : Number(ownerUserId),
        deviceUuid: toBase64Url(sodium, sodium.randombytes_buf(16)),
        deviceName,
        publicEncryptionKey: toBase64Url(sodium, encryption.publicKey),
        privateEncryptionKey: toBase64Url(sodium, encryption.privateKey),
        publicSigningKey: toBase64Url(sodium, signing.publicKey),
        privateSigningKey: toBase64Url(sodium, signing.privateKey),
        keyFingerprint: toBase64Url(sodium, fingerprint),
    };
}

export function registrationPayload(identity) {
    return {
        device_uuid: identity.deviceUuid,
        device_name: identity.deviceName,
        public_encryption_key: identity.publicEncryptionKey,
        public_signing_key: identity.publicSigningKey,
        key_fingerprint: identity.keyFingerprint,
    };
}

export function selectDeviceIdentity(identities, userId, registeredDevices = []) {
    const ownerUserId = Number(userId);
    const owned = identities.find(identity => Number(identity.ownerUserId) === ownerUserId);

    if (owned) {
        return owned;
    }

    const registered = new Map(
        registeredDevices.map(device => [device.device_uuid, device]),
    );
    const legacy = identities.find(identity => {
        if (identity.ownerUserId !== undefined && identity.ownerUserId !== null) return false;

        const device = registered.get(identity.deviceUuid);

        return device?.key_fingerprint === identity.keyFingerprint;
    });

    return legacy ? { ...legacy, ownerUserId } : null;
}

export async function ensureDeviceIdentity(userId, registeredDevices = [], deviceName = null) {
    const database = await openDatabase();
    const identities = await readAll(database);
    const existing = selectDeviceIdentity(identities, userId, registeredDevices);

    if (existing) {
        if (existing.ownerUserId !== undefined && existing.ownerUserId !== null) {
            await writeIdentity(database, existing);
        }

        return registrationPayload(existing);
    }

    const identity = await createDeviceIdentity(deviceName, userId);
    await writeIdentity(database, identity);

    return registrationPayload(identity);
}

export async function loadDeviceKeyMaterial(deviceUuid) {
    const database = await openDatabase();
    const identity = await readIdentity(database, deviceUuid);

    if (!identity) {
        throw new Error('This browser does not hold the requested E2EE device identity.');
    }

    const sodium = await initializeCrypto();

    return {
        publicEncryptionKey: fromBase64Url(sodium, identity.publicEncryptionKey),
        privateEncryptionKey: fromBase64Url(sodium, identity.privateEncryptionKey),
        publicSigningKey: fromBase64Url(sodium, identity.publicSigningKey),
        privateSigningKey: fromBase64Url(sodium, identity.privateSigningKey),
    };
}

function openDatabase() {
    if (typeof indexedDB === 'undefined') {
        return Promise.reject(new Error('IndexedDB is required for E2EE device-key storage.'));
    }

    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DATABASE_NAME, 1);
        request.onupgradeneeded = () => {
            if (!request.result.objectStoreNames.contains(STORE_NAME)) {
                request.result.createObjectStore(STORE_NAME, { keyPath: 'deviceUuid' });
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(new Error('Unable to initialize the local E2EE device store.'));
    });
}

function readAll(database) {
    return new Promise((resolve, reject) => {
        const request = database.transaction(STORE_NAME).objectStore(STORE_NAME).getAll();
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(new Error('Unable to read the local E2EE device store.'));
    });
}

function readIdentity(database, deviceUuid) {
    return new Promise((resolve, reject) => {
        const request = database.transaction(STORE_NAME).objectStore(STORE_NAME).get(deviceUuid);
        request.onsuccess = () => resolve(request.result ?? null);
        request.onerror = () => reject(new Error('Unable to read the local E2EE device identity.'));
    });
}

function writeIdentity(database, identity) {
    return new Promise((resolve, reject) => {
        const transaction = database.transaction(STORE_NAME, 'readwrite');
        transaction.objectStore(STORE_NAME).put(identity);
        transaction.oncomplete = () => resolve();
        transaction.onerror = () => reject(new Error('Unable to persist the local E2EE device identity.'));
    });
}
