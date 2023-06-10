<?php

namespace Codilar\OrderSelfApi\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;

interface OrderRepositoryInterface
{
    /**
     * Return order details of customer
     * @api
     * @param SearchCriteriaInterface $searchCriteria
     * @return OrderSearchResultInterface
     */
    public function getOrders(SearchCriteriaInterface $searchCriteria);
}
