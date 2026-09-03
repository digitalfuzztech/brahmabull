export function toBase64Url(sodium, bytes) {
    return sodium.to_base64(bytes, sodium.base64_variants.URLSAFE_NO_PADDING);
}

export function fromBase64Url(sodium, value) {
    if (typeof value !== 'string' || !/^[A-Za-z0-9_-]+$/.test(value)) {
        throw new Error('Invalid Base64URL value.');
    }

    return sodium.from_base64(value, sodium.base64_variants.URLSAFE_NO_PADDING);
}

export function concatBytes(...values) {
    const length = values.reduce((total, value) => total + value.length, 0);
    const combined = new Uint8Array(length);
    let offset = 0;

    for (const value of values) {
        combined.set(value, offset);
        offset += value.length;
    }

    return combined;
}
