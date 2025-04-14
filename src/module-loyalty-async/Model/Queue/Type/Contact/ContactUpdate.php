<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Model\Queue\Type\Contact;

use Leat\AsyncQueue\Model\Connector\ConnectorPool;
use Leat\AsyncQueue\Model\Job;
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\ResourceModel\Loyalty\AttributeResource;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Leat\LoyaltyAsync\Model\Connector\AsyncConnector;
use Leat\LoyaltyAsync\Model\Queue\Type\ContactType;
use Magento\Store\Model\StoreManagerInterface;
use Piggy\Api\Models\Contacts\Contact;

class ContactUpdate extends ContactType
{
    protected const string TYPE_CODE = 'contact_update';

    public function __construct(
        protected AttributeResource $attributeResource,
        Config $config,
        ContactResource $contactResource,
        ConnectorPool $connectorPool,
        StoreManagerInterface $storeManager,
        AsyncConnector $connector,
        array $data = []
    ) {
        parent::__construct($config, $contactResource, $connectorPool, $storeManager, $connector, $data);
    }

    /**
     * Update the Leat Contact with details from Magento.
     */
    protected function execute(): ?Contact
    {
        /** @var Job $job */
        $job = $this->getJob();
        $customerId = (int)$job->getRelationId();
        $contact = $this->contactResource->getCustomerContact($customerId);
        if (!$contact) {
            // No contact to update.
            return null;
        }

        $attributes = $this->attributeResource->getFormattedContactAttributes($job->getStoreId());
        $contactAttributes = $contact->getAttributes();
        $toUpdate = [];
        foreach ($attributes as $dataKey => $contactKey) {
            if ($dataKey === 'address') {
                $data = $this->getAddressString();
            } else {
                $data = $this->getData($dataKey);

                // always send email as all lowercase to Piggy, otherwise Leat will not accept it.
                if ($dataKey === 'email' && $data) {
                    $data = strtolower($data);
                }
            }

            if (empty($data) || $contactAttributes[$contactKey]->getValue() === $data) {
                continue;
            }

            $toUpdate[$dataKey] = $data;
        }

        if (!empty($toUpdate)) {
            $this->contactResource->updateContact($customerId, $toUpdate);
        }

        return $contact;
    }

    /**
     * @param mixed|null $address
     * @return string
     */
    public function getAddressString(mixed $address = null): string
    {
        if (!$address) {
            if ($this->getData('address') === null) {
                return '';
            }

            $address = $this->getData('address');
        }

        return sprintf(
            "%s \n %s \n %s %s (%s)",
            $address['company'] ?? '',
            is_array($address['street']) ? current($address['street']) : $address['street'],
            $address['postcode'],
            $address['city'],
            $address['country_id'],
        );
    }
}
