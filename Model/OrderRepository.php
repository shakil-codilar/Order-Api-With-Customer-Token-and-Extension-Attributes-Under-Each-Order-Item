<?php

namespace Codilar\OrderSelfApi\Model;


use Codilar\OrderSelfApi\Api\OrderRepositoryInterface;
use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Sales\Api\Data\OrderExtensionInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterfaceFactory as SearchResultFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Sales\Api\Data\ShippingAssignmentInterface;
use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Model\Order\ShippingAssignmentBuilder;
use Amasty\DeliveryDateManager\Model\DeliveryOrder\Get as DeliveryOrder;

class OrderRepository implements OrderRepositoryInterface
{
    /**
     * @var DeliveryOrder
     */
    protected $deliveryOrder;
    /**
     * @var SearchResultFactory
     */
    protected $searchResultFactory;

    /**
     * @var JoinProcessorInterface|mixed
     */
    private $extensionAttributesJoinProcessor;


    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * @var OrderExtensionFactory
     */
    private $orderExtensionFactory;

    /**
     * @var ShippingAssignmentBuilder
     */
    private $shippingAssignmentBuilder;


    /**
     * @param SearchResultFactory $searchResultFactory
     * @param JoinProcessorInterface|null $extensionAttributesJoinProcessor
     */
    public function __construct(
        SearchResultFactory          $searchResultFactory,
        JoinProcessorInterface       $extensionAttributesJoinProcessor,
        CollectionProcessorInterface $collectionProcessor,
        OrderExtensionFactory        $orderExtensionFactory,
        DeliveryOrder $deliveryOrder
    ) {
         $this->searchResultFactory = $searchResultFactory;
        $this->extensionAttributesJoinProcessor = $extensionAttributesJoinProcessor
            ?: ObjectManager::getInstance()->get(JoinProcessorInterface::class);
        $this->collectionProcessor = $collectionProcessor ?: ObjectManager::getInstance()
            ->get(\Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface::class);
        $this->orderExtensionFactory = $orderExtensionFactory ?: ObjectManager::getInstance()
            ->get(\Magento\Sales\Api\Data\OrderExtensionFactory::class);
        $this->deliveryOrder = $deliveryOrder;
    }

    /**
     * @inheritdoc
     */
    public function getOrders(SearchCriteriaInterface $searchCriteria)
    {
        $searchResult = $this->searchResultFactory->create();
        $this->extensionAttributesJoinProcessor->process($searchResult);
        $this->collectionProcessor->process($searchCriteria, $searchResult);
        $searchResult->setSearchCriteria($searchCriteria);
        foreach ($searchResult->getItems() as $order) {
            $this->setShippingAssignments( $order);
                $extensionAttributes = $order->getExtensionAttributes();

                if ($extensionAttributes === null) {
                    $extensionAttributes = $this->orderExtensionFactory->create();
                }
                if ($extensionAttributes->getAmdeliverydate() !== null) {
                    // Delivery Date entity is already loaded; no actions required
                    return $searchResult;
                }
                try {
                    $deliveryDate = $this->deliveryOrder->getByOrderId((int)$order->getEntityId());

                    $extensionAttributes->setAmdeliverydate($deliveryDate);

                    $order->setExtensionAttributes($extensionAttributes);
                } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                    // Delivery Date entity cannot be loaded for current order; no actions required
                    continue;
                }
        }
        return $searchResult;
    }

    /**
     * @param OrderInterface $order
     * @return void
     */
    private function setShippingAssignments(OrderInterface $order)
    {
        /** @var OrderExtensionInterface $extensionAttributes */
        $extensionAttributes = $order->getExtensionAttributes();

        if ($extensionAttributes === null) {
            $extensionAttributes = $this->orderExtensionFactory->create();
        } elseif ($extensionAttributes->getShippingAssignments() !== null) {
            return;
        }
        /** @var ShippingAssignmentInterface $shippingAssignment */
        if (!$this->shippingAssignmentBuilder instanceof ShippingAssignmentBuilder) {
            $this->shippingAssignmentBuilder = \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Magento\Sales\Model\Order\ShippingAssignmentBuilder::class
            );
        }
        $shippingAssignments = $this->shippingAssignmentBuilder;
        $shippingAssignments->setOrderId($order->getEntityId());
        $extensionAttributes->setShippingAssignments($shippingAssignments->create());
        $order->setExtensionAttributes($extensionAttributes);
    }
}
