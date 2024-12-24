<?php
namespace Lsa\PaymentGateway\Module;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleContextInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;

class PaymentGateway extends \Magento\Framework\Module\AbstractModule
{
    const MODULE_NAME = 'Lsa_PaymentGateway';

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    public function __construct(
        ModuleContextInterface $context,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check if the module is enabled in the admin configuration
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            'payment/lsa_paymentgateway/active',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Triggered by observers
     * Use this to process payment
     */
    public function execute(ObserverInterface $observer): void
    {
        if (!$this->isEnabled()) {
            throw new LocalizedException(__('The payment gateway is disabled.'));
        }

        // Logic of payment
    }
}
