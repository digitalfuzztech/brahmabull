import sodium from 'libsodium-wrappers';

let readyPromise;

export function initializeCrypto() {
    if (!readyPromise) {
        readyPromise = sodium.ready.then(() => {
            const required = [
                'crypto_aead_xchacha20poly1305_ietf_encrypt',
                'crypto_aead_xchacha20poly1305_ietf_decrypt',
                'crypto_box_seal',
                'crypto_box_seal_open',
                'randombytes_buf',
            ];

            if (required.some((method) => typeof sodium[method] !== 'function')) {
                throw new Error('Required Sodium E2EE primitives are unavailable.');
            }

            return sodium;
        });
    }

    return readyPromise;
}
