<?php


namespace ReportUri\Passkeys\Attestation\Format;
use ReportUri\Passkeys\Attestation\AuthenticatorData;
use ReportUri\Passkeys\WebAuthnException;

class None extends FormatBase {


    public function __construct($AttestionObject, AuthenticatorData $authenticatorData) {
        parent::__construct($AttestionObject, $authenticatorData);
    }


    /*
     * returns the key certificate in PEM format
     * @return string
     */
    public function getCertificatePem() {
        return null;
    }

    /**
     * @param string $clientDataHash
     */
    public function validateAttestation($clientDataHash) {
        // §8.7 None Attestation Statement Format:
        // "If attStmt is a properly formed attestation statement,
        //  verify that attStmt is an empty CBOR map."
        if (\count($this->_attestationObject['attStmt']) > 0) {
            throw new WebAuthnException('invalid none attestation: attStmt must be empty', WebAuthnException::INVALID_DATA);
        }

        return true;
    }

    /**
     * validates the certificate against root certificates.
     * Format 'none' does not contain any ca, so always false.
     * @param array $rootCas
     * @return boolean
     * @throws WebAuthnException
     */
    public function validateRootCertificate($rootCas) {
        return false;
    }
}
