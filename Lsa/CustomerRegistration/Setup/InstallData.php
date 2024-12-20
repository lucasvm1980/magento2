<?php

namespace Lsa\CustomerRegistration\Setup;

use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Customer\Model\Customer;
use Magento\Eav\Model\Config;

class InstallData implements InstallDataInterface
{
    private $eavSetupFactory;
    private $eavConfig;

    public function __construct(
        EavSetupFactory $eavSetupFactory,
        Config $eavConfig
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eavConfig = $eavConfig;
    }

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        // Add is_integrated attribute to customer
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

        $this->addEmailTemplate($setup);

        $setup->endSetup();
    }

    private function addEmailTemplate(ModuleDataSetupInterface $setup)
    {
        $templateTable = $setup->getTable('email_template');
        $templateContent = file_get_contents(
            BP . '/app/code/Lsa/CustomerRegistration/view/frontend/email/customer_support_email_template.html'
        );

        $setup->getConnection()->insert(
            $templateTable,
            [
                'template_code' => 'customer_support_email_template',
                'template_text' => $templateContent,
                'template_type' => 2, // HTML template
                'template_subject' => 'New Customer Registration Details',
                'template_sender_name' => 'General Contact',
                'template_sender_email' => 'general@example.com',
                'orig_template_code' => 'customer_support_email_template',
                'orig_template_variables' => json_encode([
                    'firstname' => '',
                    'lastname' => '',
                    'email' => '',
                ]),
                'added_at' => date('Y-m-d H:i:s'),
                'modified_at' => date('Y-m-d H:i:s'),
            ]
        );
    }
}
