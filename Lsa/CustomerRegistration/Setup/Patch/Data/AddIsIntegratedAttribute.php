<?php

namespace Lsa\CustomerRegistration\Setup\Patch\Data;

use Magento\Eav\Setup\EavSetupFactory;
use Magento\Eav\Model\Config;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Customer\Model\Customer;

class AddIsIntegratedAttribute implements DataPatchInterface
{
    private $eavSetupFactory;
    private $eavConfig;
    private $moduleDataSetup;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory,
        Config $eavConfig
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eavConfig = $eavConfig;
    }

    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        /** @var \Magento\Eav\Setup\EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $eavSetup->addAttribute(
            Customer::ENTITY,
            'is_integrated',
            [
                'type' => 'int',
                'label' => 'Is Integrated',
                'input' => 'boolean',
                'required' => false,
                'default' => 0,
                'visible' => true,
                'user_defined' => false,
                'position' => 999,
                'system' => 0,
            ]
        );

        $attribute = $this->eavConfig->getAttribute(Customer::ENTITY, 'is_integrated');
        $attribute->setData('used_in_forms', ['adminhtml_customer']);
        $attribute->save();

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}
