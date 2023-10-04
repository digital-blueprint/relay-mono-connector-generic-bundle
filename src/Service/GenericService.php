<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorGenericBundle\Service;

use Dbp\Relay\CoreBundle\API\UserSessionInterface;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\MonoBundle\ApiPlatform\Payment;
use Dbp\Relay\MonoBundle\BackendServiceProvider\BackendServiceInterface;
use Dbp\Relay\MonoBundle\Persistence\PaymentPersistence;
use GuzzleHttp\Client;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class GenericService implements BackendServiceInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected $userSession;

    public function __construct(
        LoggerInterface $logger,
        UserSessionInterface $userSession
    ) {
        $this->logger = $logger;
        $this->userSession = $userSession;
    }

    public function updateData(PaymentPersistence $paymentPersistence): bool
    {
        $changed = false;

        $userIdentifier = $this->userSession->getUserIdentifier();
        $paymentPersistence->setUserIdentifier($userIdentifier);

        $data = json_decode((string) $paymentPersistence->getData(), true, 512, JSON_THROW_ON_ERROR);

        $requiredPropertyNames = [
            'paymentReference',
            'amount',
            'currency',
        ];
        foreach ($requiredPropertyNames as $propertyName) {
            if (!array_key_exists($propertyName, $data)) {
                throw new ApiError(Response::HTTP_BAD_REQUEST, 'Property "'.$propertyName.'" missing!');
            }
        }
        if (
            !array_key_exists('companyName', $data)
            && (
                !array_key_exists('givenName', $data)
                || !array_key_exists('familyName', $data)
            )
        ) {
            if (!array_key_exists($propertyName, $data)) {
                throw new ApiError(Response::HTTP_BAD_REQUEST, 'Either properties givenName and familiyName or property companyName missing!');
            }
        }

        $extractPropertyNames = [
            'paymentReference',
            'amount',
            'currency',
            'alternateName',
            'honorificPrefix',
            'givenName',
            'familyName',
            'companyName',
            'honorificSuffix',
            'recipient',
            'paymentMethod',
            'dataProtectionDeclarationUrl',
        ];

        foreach ($extractPropertyNames as $propertyName) {
            $setMethod = 'set'.ucfirst($propertyName);
            if (
                array_key_exists($propertyName, $data)
                && method_exists($paymentPersistence, $setMethod)
            ) {
                $paymentPersistence->{$setMethod}((string) $data[$propertyName]);
                $changed = true;
            }
        }

        return $changed;
    }

    public function updateEntity(PaymentPersistence $paymentPersistence, Payment $payment): bool
    {
        return false;
    }

    public function notify(PaymentPersistence $paymentPersistence): bool
    {
        $notified = false;

        if (
            !$paymentPersistence->getNotifiedAt()
            && $paymentPersistence->getNotifyUrl()
        ) {
            try {
                $client = new Client();
                $response = $client->request('GET', $paymentPersistence->getNotifyUrl());
                if (
                    $response->getStatusCode() >= 200
                    && $response->getStatusCode() < 300
                ) {
                    $notified = true;
                }
            } catch (\Exception $e) {
                $this->logger->error('Communication error with backend!', ['exception' => $e]);
                throw new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, 'Communication error with backend!');
            }
        }

        return $notified;
    }

    public function cleanup(PaymentPersistence $paymentPersistence): bool
    {
        return true;
    }
}
