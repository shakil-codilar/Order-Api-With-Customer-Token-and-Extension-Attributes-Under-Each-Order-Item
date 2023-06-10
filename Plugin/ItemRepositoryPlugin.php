<?php

namespace Codilar\OrderSelfApi\Plugin;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Api\Data\OrderItemExtensionFactory;
use Magento\Sales\Api\Data\OrderItemSearchResultInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ResourceModel\ProductFactory;

/**
 * Class OrderItemRepositoryPlugin
 */
class ItemRepositoryPlugin
{

    /**
     * @var OrderItemExtensionFactory
     */
    protected OrderItemExtensionFactory $orderItemExtensionFactory;

    /**
     * @var ProductRepositoryInterface
     */
    protected ProductRepositoryInterface $productFactory;

    /**
     * @var StoreManagerInterface $storeManager
     */
    protected StoreManagerInterface $storeManager;

    protected ProductFactory $productResource;

    /**
     * @param OrderItemExtensionFactory $orderItemExtensionFactory
     * @param ProductRepositoryInterface $productFactory
     * @param StoreManagerInterface $storeManager
     * @param ProductFactory $productResource
     */
    public function __construct(
        OrderItemExtensionFactory $orderItemExtensionFactory,
        ProductRepositoryInterface $productFactory,
        StoreManagerInterface $storeManager,
        ProductFactory $productResource
    ) {
        $this->orderItemExtensionFactory = $orderItemExtensionFactory;
        $this->productFactory = $productFactory;
        $this->storeManager = $storeManager;
        $this->productResource = $productResource;
    }

    /**
     * @param OrderItemInterface $orderItem
     * @return OrderItemInterface
     * @throws NoSuchEntityException|LocalizedException
     */
    protected function addProductAttributesData(OrderItemInterface $orderItem): OrderItemInterface
    {
        $productResource = $this->productResource->create();
        $product = $this->productFactory->getById($orderItem->getProductId());
        $store = $this->storeManager->getStore();
        $productImageUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' .$product->getImage();
        $color = $product->getData('color');
        $material = $product->getData('material');
        /*Selected Capacity Attribute option id */
        $capacityOptionId = $product->getData('capacity');
        /* Get Label of selected Capacity attribute option */
        $capacity = $productResource->getAttribute('capacity')->getSource()->getOptionText($capacityOptionId);

        $customAttribute = array($productImageUrl,$color,$material,$capacity);

        if ($customAttribute) {
            $orderItemExtension = $this->orderItemExtensionFactory->create();
            $orderItemExtension->setItemColor($color);
            $orderItemExtension->setItemMaterial($material);
            $orderItemExtension->setItemCapacity($capacity);
            $orderItemExtension->setItemImage($productImageUrl);

            $orderItem->setExtensionAttributes($orderItemExtension);
        }

        return $orderItem;
    }

    /**
     * @param OrderItemRepositoryInterface $subject
     * @param OrderItemSearchResultInterface $searchResult
     * @return OrderItemSearchResultInterface
     * @throws NoSuchEntityException|LocalizedException
     */
    public function afterGetList(OrderItemRepositoryInterface $subject, OrderItemSearchResultInterface $searchResult): OrderItemSearchResultInterface
    {
        $orders = $searchResult->getItems();

        foreach ($orders as &$order) {
            $order = $this->addProductAttributesData($order);
        }

        return $searchResult;
    }
}
