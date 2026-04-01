<?php

namespace App\Domains\Migration\DTOs;

class ContactImportRow extends AbstractImportRow
{
    public function __construct(
        int $sourceRow,
        public readonly string $type,
        public readonly string $name,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $address = null,
        public readonly ?string $zip = null,
        public readonly ?string $city = null,
        public readonly ?string $country = null,
        public readonly ?string $vatNumber = null,
        public readonly ?string $reference = null,
        public readonly ?string $notes = null,
    ) {
        parent::__construct($sourceRow);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'zip' => $this->zip,
            'city' => $this->city,
            'country' => $this->country,
            'vat_number' => $this->vatNumber,
            'reference' => $this->reference,
            'notes' => $this->notes,
        ];
    }
}
