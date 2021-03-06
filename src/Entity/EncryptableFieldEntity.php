<?php

namespace App\Entity;

class EncryptableFieldEntity
{
    /**
     * @var array
     */
    protected $hashOptions = ['cost' => 12];

    /**
     * @param string $value
     *
     * @return bool|string
     */
    protected function encryptField($value)
    {
        return password_hash($value, PASSWORD_BCRYPT, $this->hashOptions);
    }

    /**
     * @param string $encryptedValue
     * @param string $value
     *
     * @return bool Whether the encrypted value corresponds the value
     */
    protected function verifyEncryptedFieldValue($encryptedValue, $value): bool
    {
        return password_verify($value, $encryptedValue);
    }
}
