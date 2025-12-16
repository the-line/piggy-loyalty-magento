<?php

namespace Leat\Loyalty\Console;

use Leat\Loyalty\Model\CustomerContactLink;
use Leat\LoyaltyAsync\Model\Queue\Builder\Service\ContactBuilder;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCustomers extends Command
{
    public function __construct(
        protected ContactBuilder $contactBuilder,
        protected CustomerContactLink $contact,
        protected CustomerCollectionFactory $customerCollectionFactory,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('leat:sync:customers');
        $this->setDescription('Sync all exisiting customers without contact UUID to Leat');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("Getting all customers not having a Leat contact UUID...");

        $customersWithoutContactUuid = $this->customerCollectionFactory->create()->addFieldToFilter('contact_uuid', ['null' => true])->getItems();

        $output->writeln("Found " . count($customersWithoutContactUuid) . " customers without Leat contact UUID.");
        foreach ($customersWithoutContactUuid as $customer) {
            $output->writeln("Getting details for customer " . $customer->getId() . ": " . $customer->getEmail());
            try {
                if (!$this->contact->hasCreateJob($customer->getId())) {
                    $output->writeln("Creating contact for: " . $customer->getEmail());
                    $this->contactBuilder->addNewContact($this->contact->getCustomer($customer->getId()));
                } else {
                    $output->writeln("Contact creation job already exists for: " . $customer->getEmail() . ", skipping...");
                }
            } catch (\Exception $e) {
                $output->writeln("An error occurred: " . $e->getMessage());
            }
        }
        $output->writeln("Done.");
        return Command::SUCCESS;
    }
}
