<?php

namespace App\Domain\Integrations\Services;

/**
 * Signs TRA messages with the shop's own certificate (.pfx): SHA-1 with RSA, then base64, exactly as the TRA
 * VFD documentation requires. Nothing here is stored; the caller passes the certificate and its password.
 */
class FiscalSigner
{
    private \OpenSSLAsymmetricKey $privateKey;

    private string $certificate;

    public function __construct(string $pfxBinary, string $password)
    {
        $parts = [];

        if (! function_exists('openssl_pkcs12_read') || ! @openssl_pkcs12_read($pfxBinary, $parts, $password)) {
            throw new FiscalException('The TRA certificate could not be opened. Check the file and its password.');
        }

        $key = openssl_pkey_get_private($parts['pkey']);

        if ($key === false) {
            throw new FiscalException('The TRA certificate has no usable private key.');
        }

        $this->privateKey = $key;
        $this->certificate = (string) $parts['cert'];
    }

    /** Base64 signature of the exact bytes given. */
    public function sign(string $payload): string
    {
        if (! openssl_sign($payload, $signature, $this->privateKey, OPENSSL_ALGO_SHA1)) {
            throw new FiscalException('Signing the TRA message failed.');
        }

        return base64_encode($signature);
    }

    /** The full message TRA expects: the signed block inside <EFDMS>, followed by its signature. */
    public function envelope(string $payload): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><EFDMS>'.$payload.'<EFDMSSIGNATURE>'.$this->sign($payload).'</EFDMSSIGNATURE></EFDMS>';
    }

    /** Checks a TRA response against the certificate's public key (the response signs the block it wraps). */
    public function verify(string $payload, string $signatureBase64): bool
    {
        $public = openssl_pkey_get_public($this->certificate);

        return $public !== false && openssl_verify($payload, (string) base64_decode($signatureBase64, true), $public, OPENSSL_ALGO_SHA1) === 1;
    }

    /** The certificate serial as TRA's header wants it: hex pairs separated by spaces, then base64. */
    public function certSerialHeader(): string
    {
        $parsed = openssl_x509_parse($this->certificate);
        $hex = strtolower((string) ($parsed['serialNumberHex'] ?? ''));

        if ($hex === '') {
            throw new FiscalException('The TRA certificate has no serial number.');
        }

        if (strlen($hex) % 2 === 1) {
            $hex = '0'.$hex;
        }

        return base64_encode(implode(' ', str_split($hex, 2)));
    }
}
