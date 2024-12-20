<?php
namespace Lsa\CustomerRegistration\Plugin;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ResourceConnection;

class CustomerRegistrationPlugin
{
    private $logger;
    private $curl;
    private $transportBuilder;
    private $storeManager;
    private $resourceConnection;

    public function __construct(
        LoggerInterface $logger,
        Curl $curl,
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection
    ) {
        $this->logger = $logger;
        $this->curl = $curl;
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Plugin for Customer Repository's save method
     */
    public function beforeSave(
        CustomerRepositoryInterface $subject,
        CustomerInterface $customer
    ) {
        // Remove whitespaces from the first name
        $firstName = preg_replace('/\s+/', '', $customer->getFirstname());
        $customer->setFirstname($firstName);

        return [$customer];
    }

    /**
     * Plugin for Customer Repository's save method (After Save)
     */
    public function afterSave(
        CustomerRepositoryInterface $subject,
        CustomerInterface $resultCustomer
    ) {
        $this->logCustomerData($resultCustomer);
        $this->sendEmailToCustomerSupport($resultCustomer);
        $this->integrateWithThirdParty($resultCustomer);

        return $resultCustomer;
    }

    private function logCustomerData(CustomerInterface $customer)
    {
        $data = [
            'date' => date('Y-m-d H:i:s'),
            'firstname' => $customer->getFirstname(),
            'lastname' => $customer->getLastname(),
            'email' => $customer->getEmail(),
        ];

        $this->logger->info('Customer Registration:', $data);
    }

    private function sendEmailToCustomerSupport(CustomerInterface $customer)
    {
        $supportEmail = $this->storeManager->getStore()->getConfig('trans_email/ident_support/email');

        $transport = $this->transportBuilder
            ->setTemplateIdentifier('customer_support_email_template') //Taking template from admin
            ->setTemplateOptions([
                'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                'store' => $this->storeManager->getStore()->getId(),
            ])
            ->setTemplateVars([
                'firstname' => $customer->getFirstname(),
                'lastname' => $customer->getLastname(),
                'email' => $customer->getEmail(),
            ])
            ->setFromByScope('general')
            ->addTo($supportEmail)
            ->getTransport();

        $transport->sendMessage();
    }

    private function integrateWithThirdParty(CustomerInterface $customer)
    {
        $data = [
            'firstname' => $customer->getFirstname(),
            'lastname' => $customer->getLastname(),
            'email' => $customer->getEmail(),
        ];

        try {
            // Log the data being sent
            $this->logger->debug('Sending data to third-party API:', $data);

            // Prepare the request
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->post('https://httpbin.org/post', json_encode($data)); // Send JSON payload

            // Get and decode the response
            $response = json_decode($this->curl->getBody(), true);

            // Log the raw response
            $this->logger->debug('Third-party API response:', ['response' => $response]);

            if (isset($response['json'])) {
                // Update the attribute directly for performance
                $this->updateCustomerAttributeDirectly($customer->getId(), 'is_integrated', 1);
                $this->logger->info('Integration successful. Attribute "is_integrated" set to 1.');
            } else {
                $this->logger->error('Invalid response format from third-party API.', ['response' => $response]);
            }
        } catch (\Exception $e) {
            // Log the error message
            $this->logger->error('Third-party integration failed: ' . $e->getMessage());

            // Update the attribute directly for failure case
            $this->updateCustomerAttributeDirectly($customer->getId(), 'is_integrated', 0);
        }
    }

    private function updateCustomerAttributeDirectly($customerId, $attributeCode, $value)
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('customer_entity_int');

        // Get the attribute ID for the given code
        $attributeId = $connection->fetchOne(
            "SELECT attribute_id FROM eav_attribute WHERE attribute_code = :attribute_code AND entity_type_id = 1",
            ['attribute_code' => $attributeCode]
        );

        if ($attributeId) {
            // Update or insert the attribute value
            $connection->insertOnDuplicate(
                $tableName,
                [
                    'attribute_id' => $attributeId,
                    'entity_id' => $customerId,
                    'value' => $value,
                ],
                ['value'] // Fields to update on duplicate
            );

            $this->logger->info("Customer attribute '{$attributeCode}' updated directly for customer ID: {$customerId}");
        } else {
            $this->logger->error("Attribute '{$attributeCode}' not found.");
        }
    }
}
