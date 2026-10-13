import { createHmac, timingSafeEqual } from 'node:crypto';

const ALLOWED_METHOD_TYPES = new Set([
    'gcash',
    'paymaya',
    'qrph',
    'card',
    'dob',
    'dob_ubp',
    'brankas'
]);

const DEFAULT_METHOD_TYPES = {
    e_wallet: ['gcash', 'paymaya', 'qrph'],
    bank_transfer: ['dob', 'dob_ubp', 'brankas'],
    card: ['card']
};

const parseMethodTypes = (value, fallback) => {
    const configured = String(value || '').trim();
    const methods = configured
        ? configured.split(',').map((method) => method.trim().toLowerCase()).filter(Boolean)
        : fallback;
    if (methods.some((method) => !ALLOWED_METHOD_TYPES.has(method))) {
        throw new Error('PAYMONGO method type configuration contains an unsupported method.');
    }
    return [...new Set(methods)];
};

export const getPayMongoConfig = (env = process.env) => {
    const mode = String(env.PAYMONGO_MODE || 'test').trim().toLowerCase();
    if (!['test', 'live'].includes(mode)) {
        throw new Error('PAYMONGO_MODE must be test or live.');
    }
    const secretKey = String(env.PAYMONGO_SECRET_KEY || '').trim();
    if (secretKey && !secretKey.startsWith(mode === 'test' ? 'sk_test_' : 'sk_live_')) {
        throw new Error(`PAYMONGO_SECRET_KEY must be a ${mode} mode secret key.`);
    }
    if (mode === 'live' && env.NODE_ENV !== 'production') {
        throw new Error('Live PayMongo mode is allowed only when NODE_ENV=production.');
    }
    const baseUrl = String(env.APP_BASE_URL || 'http://localhost:3000').trim().replace(/\/+$/, '');
    let parsedBaseUrl;
    try {
        parsedBaseUrl = new URL(baseUrl);
    } catch {
        throw new Error('APP_BASE_URL must be a valid absolute URL.');
    }
    if (mode === 'live' && parsedBaseUrl.protocol !== 'https:') {
        throw new Error('APP_BASE_URL must use HTTPS in live mode.');
    }

    return {
        mode,
        secretKey,
        webhookSecret: String(env.PAYMONGO_WEBHOOK_SECRET || '').trim(),
        baseUrl,
        passOnFees: String(env.PAYMONGO_PASS_ON_FEES || 'true').trim().toLowerCase() !== 'false',
        methodTypes: {
            e_wallet: parseMethodTypes(env.PAYMONGO_EWALLET_METHOD_TYPES, DEFAULT_METHOD_TYPES.e_wallet),
            bank_transfer: parseMethodTypes(env.PAYMONGO_BANK_METHOD_TYPES, DEFAULT_METHOD_TYPES.bank_transfer),
            card: parseMethodTypes(env.PAYMONGO_CARD_METHOD_TYPES, DEFAULT_METHOD_TYPES.card)
        }
    };
};

export const amountToCentavos = (amount) => {
    const numericAmount = Number(amount);
    if (!Number.isFinite(numericAmount) || numericAmount <= 0) {
        throw new Error('Payment amount must be greater than zero.');
    }
    const centavos = Math.round(numericAmount * 100);
    if (!Number.isSafeInteger(centavos) || centavos <= 0) {
        throw new Error('Payment amount is outside PayMongo limits.');
    }
    return centavos;
};

export const isExpectedPayMongoPaymentAmount = ({
    expectedCentavos,
    merchantAmount,
    chargedCentavos,
    feeCentavos,
    passOnFees
}) => {
    if (![expectedCentavos, merchantAmount, chargedCentavos, feeCentavos]
        .every(Number.isSafeInteger)
        || expectedCentavos <= 0
        || merchantAmount < 0
        || chargedCentavos < expectedCentavos
        || feeCentavos < 0) return false;

    return passOnFees
        ? merchantAmount === expectedCentavos && chargedCentavos - feeCentavos === merchantAmount
        : chargedCentavos === expectedCentavos && merchantAmount + feeCentavos === expectedCentavos;
};

export const verifyPayMongoWebhookSignature = (signatureHeader, rawBody, webhookSecret, mode) => {
    if (!signatureHeader || !Buffer.isBuffer(rawBody) || !webhookSecret) return false;
    const signatureParts = Object.fromEntries(
        String(signatureHeader).split(',').map((part) => {
            const separator = part.indexOf('=');
            return separator < 0
                ? ['', '']
                : [part.slice(0, separator).trim(), part.slice(separator + 1).trim()];
        }).filter(([key, value]) => key && value)
    );
    const timestamp = signatureParts.t;
    const suppliedSignature = signatureParts[mode === 'live' ? 'li' : 'te'];
    if (!timestamp || !suppliedSignature || !/^[a-f\d]+$/i.test(suppliedSignature)) return false;

    const expectedSignature = createHmac('sha256', webhookSecret)
        .update(Buffer.concat([Buffer.from(`${timestamp}.`), rawBody]))
        .digest();
    const actualSignature = Buffer.from(suppliedSignature, 'hex');
    return actualSignature.length === expectedSignature.length
        && timingSafeEqual(actualSignature, expectedSignature);
};

export const createPayMongoCheckoutSession = async (config, attributes, idempotencyKey) => {
    const response = await fetch('https://api.paymongo.com/v2/checkout_sessions', {
        method: 'POST',
        headers: {
            Authorization: `Basic ${Buffer.from(`${config.secretKey}:`).toString('base64')}`,
            'Content-Type': 'application/json',
            'Idempotency-Key': idempotencyKey
        },
        body: JSON.stringify({ data: { attributes } }),
        signal: AbortSignal.timeout(20000)
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) {
        const apiMessage = result.errors?.[0]?.detail || result.errors?.[0]?.code;
        const error = new Error('PayMongo could not create the checkout session.');
        error.providerStatus = response.status;
        error.providerCode = typeof apiMessage === 'string' ? apiMessage.slice(0, 100) : '';
        throw error;
    }
    return result.data;
};

export const retrievePayMongoCheckoutSession = async (config, sessionId) => {
    const response = await fetch(`https://api.paymongo.com/v2/checkout_sessions/${encodeURIComponent(sessionId)}`, {
        headers: {
            Authorization: `Basic ${Buffer.from(`${config.secretKey}:`).toString('base64')}`
        },
        signal: AbortSignal.timeout(15000)
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) {
        const error = new Error('PayMongo could not verify the existing checkout session.');
        error.providerStatus = response.status;
        throw error;
    }
    return result.data;
};
