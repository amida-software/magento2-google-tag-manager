<?php
/**
 * Copyright © MagePal LLC. All rights reserved.
 * See COPYING.txt for license details.
 * http://www.magepal.com | support@magepal.com
 */

namespace MagePal\GoogleTagManager\Block\Data;

use Magento\Catalog\Model\Category as ProductCategory;
use Magento\Catalog\Helper\Data;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use MagePal\GoogleTagManager\Block\DataLayer;
use MagePal\GoogleTagManager\DataLayer\CategoryData\CategoryProvider;
use MagePal\GoogleTagManager\Model\DataLayerEvent;
use Magento\Store\Model\StoreManagerInterface;
use Amida\Catalog\Block\Product\ListProduct;

class Category extends Template
{
    /**
     * Catalog data
     *
     * @var Data
     */
    protected $_catalogData = null;

    /**
     * Core registry
     *
     * @var Registry
     */
    protected $_coreRegistry = null;
    /**
     * @var CategoryProvider
     */
    private $categoryProvider;

    /**
     * @var StoreManagerInterface\
     */
    protected $storeManager;

    /**
     * @var ListProduct
     */
    protected $listingBlock;

    /**
     * @param  Context  $context
     * @param  Registry  $registry
     * @param  Data  $catalogData
     * @param  CategoryProvider  $categoryProvider
     * @param  array  $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        Data $catalogData,
        CategoryProvider $categoryProvider,
        StoreManagerInterface $storeManager,
        ListProduct $listProduct,
        array $data = []
    ) {
        $this->_catalogData = $catalogData;
        $this->_coreRegistry = $registry;
        parent::__construct($context, $data);
        $this->categoryProvider = $categoryProvider;
        $this->storeManager = $storeManager;
        $this->listingBlock = $listProduct;
    }

    /**
     * Retrieve current category model object
     *
     * @return \Magento\Catalog\Model\Category
     */
    public function getCurrentCategory()
    {
        if (!$this->hasData('current_category')) {
            $this->setData('current_category', $this->_coreRegistry->registry('current_category'));
        }
        return $this->getData('current_category');
    }

    /**
     * Add category data to datalayer
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        $tm = $this->getParentBlock();
        $category = $this->getCurrentCategory();

        if ($category) {

            $items = $this->getItemsForCategory();

            $data = [
                'event' => DataLayerEvent::CATEGORY_PAGE_EVENT,
                'ecommerce' => [
                    'currency' => $this->getCurrencyName(),
                    'value' => $this->calculateTotalValue($items),
                    'items' => $items
                ]
            ];

            $tm->addCustomDataLayerByEvent(DataLayerEvent::CATEGORY_PAGE_EVENT, $data);
        }

        return $this;
    }

    private function getItemsForCategory($category)
    {
        $items = [];

        foreach ($this->listingBlock->getLoadedProductCollection() as $product) {
            $items[] = [
                'item_name' => $product->getName(),
                'item_id' => $product->getSku(),
                'price' => $product->getPrice(),
                'currency' => $this->getCurrencyName(),
                'item_brand' => $product->getBrand(),
                'item_category' => $category->getName(),
                'item_variant' => $product->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE ? '' : $product->getAttributeText('color'),
                'quantity' => 1
            ];
        }

        return $items;
    }


    public function getCategoryPath()
    {
        $titleArray = [];
        $breadCrumbs = $this->_catalogData->getBreadcrumbPath();

        foreach ($breadCrumbs as $breadCrumb) {
            $titleArray[] = $breadCrumb['label'];
        }

        return implode(" > ", $titleArray);
    }

    protected function getCurrencyName()
    {
        return $this->storeManager->getStore()->getCurrentCurrencyCode();
    }

    private function calculateTotalValue($items)
    {
        $totalValue = 0;
        foreach ($items as $item) {
            $totalValue += $item['price'] * $item['quantity'];
        }
        return $totalValue;
    }
}
