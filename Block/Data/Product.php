<?php
/**
 * Copyright © MagePal LLC. All rights reserved.
 * See COPYING.txt for license details.
 * http://www.magepal.com | support@magepal.com
 */

namespace MagePal\GoogleTagManager\Block\Data;

use Exception;
use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Helper\Data;
use MagePal\GoogleTagManager\Block\DataLayer;
use MagePal\GoogleTagManager\DataLayer\ProductData\ProductProvider;
use MagePal\GoogleTagManager\Helper\Product as ProductHelper;
use MagePal\GoogleTagManager\Model\DataLayerEvent;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use MagePal\GoogleTagManager\Helper\Data as MagepalHelper;
use MagePal\GoogleTagManager\Model\Cart as GtmCartModel;

class Product extends AbstractProduct
{
    /**
     * @var GtmCartModel
     */
    protected $gtmCart;

    /**
     * @var CheckoutSession
     */
    protected $session;

    /**
     * Catalog data
     *
     * @var Data
     */
    protected $catalogHelper = null;
    /**
     * @var ProductHelper
     */

    /**
     * @var CurrencyFactory
     */
    protected $currencyFactory;

    /**
     * @var StoreManagerInterface\
     */
    protected $storeManager;

    /**
     * @var MagepalHelper
     */
    protected $magepalHelper;

    private $productHelper;
    /**
     * @var ProductProvider
     */
    private $productProvider;

    /**
     * @param  Context  $context
     * @param  ProductHelper  $productHelper
     * @param  ProductProvider  $productProvider
     * @param  array  $data
     */
    public function __construct(
        Context $context,
        ProductHelper $productHelper,
        ProductProvider $productProvider,
        StoreManagerInterface $storeManager,
        CurrencyFactory $currencyFactory,
        MagepalHelper $magepalHelper,
        GtmCartModel $gtmCart,
        CheckoutSession $session,
        array $data = []
    ) {
        $this->catalogHelper = $context->getCatalogHelper();
        parent::__construct($context, $data);
        $this->productHelper = $productHelper;
        $this->productProvider = $productProvider;
        $this->storeManager = $storeManager;
        $this->currencyFactory = $currencyFactory;
        $this->magepalHelper = $magepalHelper;
        $this->gtmCart = $gtmCart;
        $this->session = $session;
    }

    /**
     * Add product data to datalayer
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        if ($this->canAddEvent()) {
            /** @var $tm DataLayer */
            $tm = $this->getParentBlock();

            if ($product = $this->getProduct()) {
                $productData = [
                    'item_name' => $product->getName(),
                    'item_id' => $product->getSku(),
                    'price' => $this->productHelper->getProductPrice($product),
                    'currency' => $this->getCurrencyName(),
                    'item_brand' => $product->getAttributeText('manufacturer'),
                    'item_category' => $this->getProductCategoryName(),
                    'item_variant' => $product->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE ? '' : $product->getAttributeText('color')
                ];

                $productData = $this->productProvider->setProduct($product)->setProductData($productData)->getData();

                $data = [
                    'event' => DataLayerEvent::GA4_VIEW_ITEM,
                    'ecommerce' => [
                        'value' => $this->productHelper->getProductPrice($product),
                        'currency' => $this->getCurrencyName(),
                        'items' => $productData
                    ],

                ];

                $tm->addVariable('list', 'detail');
                $tm->addCustomDataLayerByEvent(DataLayerEvent::GA4_VIEW_ITEM, $data);
            }
        }

        return $this;
    }

    /**
     * Get category name from breadcrumb
     *
     * @return string
     */
    protected function getProductCategoryName()
    {
        $categoryName = '';

        try {
            $categoryArray = $this->getBreadCrumbPath();

            if (count($categoryArray) > 1) {
                end($categoryArray);
                $categoryName = prev($categoryArray);
            } elseif ($this->getProduct()) {
                $category = $this->getProduct()->getCategoryCollection()->addAttributeToSelect('name')->getFirstItem();
                $categoryName = ($category) ? $category->getName() : '';
            }
        } catch (Exception $e) {
            $categoryName = '';
        }

        return $categoryName;
    }

    /**
     * Get bread crumb path
     *
     * @return array
     */
    protected function getBreadCrumbPath()
    {
        $titleArray = [];
        $breadCrumbs = $this->catalogHelper->getBreadcrumbPath() ?? [];

        foreach ($breadCrumbs as $breadCrumb) {
            $titleArray[] = $breadCrumb['label'];
        }

        return $titleArray;
    }

    protected function getCurrencyName()
    {
        return $this->storeManager->getStore()->getCurrentCurrencyCode();
    }

    private function canAddEvent()
    {
        if ($this->session->getCartWasUpdated() && $this->gtmCart->getQuote()->getItemsSummaryQty()) {
            return false;
        }

        return true;
    }
}
