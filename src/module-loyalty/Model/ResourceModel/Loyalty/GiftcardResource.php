<?php
/**
 * GiftcardResource
 *
 * @copyright Copyright © 2025 Bold. All rights reserved.
 * @author    luuk@boldcommerce.nl
 */
declare(strict_types=1);

namespace Leat\Loyalty\Model\ResourceModel\Loyalty;

use Magento\Framework\Exception\LocalizedException;
use Piggy\Api\Mappers\Giftcards\GiftcardTransactionMapper;
use Piggy\Api\Models\Giftcards\Giftcard;
use Piggy\Api\Models\Giftcards\GiftcardProgram;
use Piggy\Api\Models\Giftcards\GiftcardTransaction;

class GiftcardResource extends AbstractLeatResource
{
    protected const string OAUTH_GIFTCARD_TRANSACTION = '/api/v3/oauth/clients/giftcard-transactions';

    public const string BUYREQUEST_OPTION_IS_GIFT = 'leat_giftcard_is_gift';
    public const string BUYREQUEST_OPTION_RECIPIENT_EMAIL = 'leat_giftcard_recipient_email';
    public const string BUYREQUEST_OPTION_RECIPIENT_FIRSTNAME = 'leat_giftcard_recipient_firstname';
    public const string BUYREQUEST_OPTION_RECIPIENT_LASTNAME = 'leat_giftcard_recipient_lastname';
    public const string BUYREQUEST_OPTION_SENDER_MESSAGE = 'leat_giftcard_sender_message';

    public const string EMAIL_SUCCESSFULLY_SENT = 'Email successfully sent';

    /**
     * @param string $giftcardProgramUUID
     * @param int $type
     * @param int|null $storeId
     * @return Giftcard
     * @throws LocalizedException
     */
    public function createGiftcard(string $giftcardProgramUUID, int $type, ?int $storeId = null): Giftcard
    {
        return $this->executeApiRequest(
            function () use ($type, $giftcardProgramUUID, $storeId) {
                $client = $this->getClient($storeId);
                return $client->giftcards->create(
                    $giftcardProgramUUID,
                    $type
                );
            },
            'Error creating giftcard'
        );
    }

    /**
     * @param $giftcardUUID
     * @param int|null $storeId
     * @return Giftcard
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getGiftcard($giftcardUUID, ?int $storeId = null): Giftcard
    {
        return $this->executeApiRequest(
            function () use ($giftcardUUID, $storeId) {
                $client = $this->getClient($storeId);
                return $client->giftcards->get($giftcardUUID);
            },
            'Error getting giftcard'
        );
    }

    /**
     * @param $hash
     * @param int|null $storeId
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function findGiftcardByHash($hash, ?int $storeId = null): Giftcard
    {
        return $this->executeApiRequest(
            function () use ($hash, $storeId) {
                $client = $this->getClient($storeId);
                return $client->giftcards->findOneBy($hash);
            },
            'Error finding giftcard by hash'
        );
    }

    /**
     * @param $hash
     * @param int|null $storeId
     * @return GiftcardProgram[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getGiftcardPrograms(?int $storeId = null): array
    {
        return $this->executeApiRequest(
            function () use ($storeId) {
                $client = $this->getClient($storeId);
                return $client->giftcardProgram->list();
            },
            'Error finding giftcard by hash'
        );
    }

    /**
     * Retrieve all rewards for all shops
     *
     * @return array
     */
    public function getAllGiftcardPrograms(): array
    {
        $result = [];
        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int) $store->getId();
            $shopUuid = $this->getShopUuid($storeId);
            if (!$shopUuid) {
                continue;
            }
            try {
                if (!$this->config->getIsEnabled($storeId)) {
                    continue;
                }

                if (isset($result[$shopUuid])) {
                    continue;
                }

                $result[$shopUuid] = $this->getGiftcardPrograms($storeId);
            } catch (LocalizedException $e) {
                $this->getLogger('giftcard_program')->log(
                    sprintf('Error fetching giftcard programs for shop %s: %s', $shopUuid, $e->getMessage())
                );
                $result[$shopUuid] = [];
            }
        }

        return $result;
    }

    /**
     * @param string|null $giftcardProgramUuid
     * @param int $page
     * @param int $limit
     * @param int|null $storeId
     * @return GiftcardTransaction[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getGiftcardTransactions(
        ?string $giftcardProgramUuid = null,
        int $page = 1,
        int $limit = 30,
        ?int $storeId = null
    ): array {
        return $this->executeApiRequest(
            function () use ($giftcardProgramUuid, $page, $limit, $storeId) {
                $client = $this->getClient($storeId);
                return $client->giftcardTransactions->list(
                    $giftcardProgramUuid,
                    $page,
                    $limit
                );
            },
            'Error retrieving giftcard transactions'
        );
    }

    /**
     * @param string $giftcardTransactionUuid
     * @param int|null $storeId
     * @return GiftcardTransaction
     * @throws LocalizedException
     */
    public function getGiftcardTransactionByUUID(
        string $giftcardTransactionUuid,
        ?int $storeId = null
    ): GiftcardTransaction {
        return $this->executeApiRequest(
            function () use ($giftcardTransactionUuid, $storeId) {
                $client = $this->getClient($storeId);
                return $client->giftcardTransactions->get($giftcardTransactionUuid);
            },
            'Error retrieving giftcard transaction by UUID'
        );
    }

    /**
     * @param string $giftCardUUID
     * @param int $amountInCents
     * @param array|null $customAttributes
     * @param int|null $storeId
     * @return GiftcardTransaction
     * @throws LocalizedException
     */
    public function createGiftcardTransaction(
        string $giftCardUUID,
        int $amountInCents,
        ?array $customAttributes = [],
        ?int $storeId = null
    ): GiftcardTransaction {
        return $this->executeApiRequest(
            function () use ($giftCardUUID, $amountInCents, $customAttributes, $storeId) {
                $client = $this->getClient($storeId);
                $shopUUID = $this->getShopUUID($storeId);
                $response = $client->post(self::OAUTH_GIFTCARD_TRANSACTION, [
                    'shop_uuid' => $shopUUID,
                    'giftcard_uuid' => $giftCardUUID,
                    'amount_in_cents' => $amountInCents,
                    'custom_attributes' => $customAttributes
                ]);

                $mapper = new GiftcardTransactionMapper();

                return $mapper->map($response->getData());
            },
            'Error creating giftcard transaction'
        );
    }

    /**
     * @param string $giftcardTransactionUuid
     * @param int|null $storeId
     * @return GiftcardTransaction
     * @throws LocalizedException
     */
    public function reverseGiftcardTransaction(
        string $giftcardTransactionUuid,
        ?int $storeId = null
    ): GiftcardTransaction {
        return $this->executeApiRequest(
            function () use ($giftcardTransactionUuid, $storeId) {
                $client = $this->getClient($storeId);
                return $client->giftcardTransactions->reverse($giftcardTransactionUuid);
            },
            'Error reversing giftcard transaction'
        );
    }

    public function sendGiftcardEmail(
        string $giftcardUUID,
        string $contactUUID,
        string $emailUUID = null,
        array $mergeTags = [],
        ?int $storeId = null
    ) {
        return $this->executeApiRequest(
            function () use ($giftcardUUID, $contactUUID, $emailUUID, $mergeTags, $storeId) {
                $client = $this->getClient($storeId);
                $postBody = [
                    'contact_uuid' => $contactUUID,
                    'merge_tags' => $mergeTags
                ];
                if ($emailUUID) {
                    $postBody['email_uuid'] = $emailUUID;
                }

                $response = $client->post(
                    "/api/v3/oauth/clients/giftcards/{$giftcardUUID}/send-by-email",
                    $postBody
                );
                return $response->getData() === self::EMAIL_SUCCESSFULLY_SENT;
            },
            'Error reversing giftcard transaction'
        );
    }
}
