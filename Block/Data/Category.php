<?php
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

class Category extends Template
{
    protected $_catalogData = null;
    protected $_coreRegistry = null;
    private $categoryProvider;
    protected $storeManager;

    public function __construct(
        Context $context,
        Registry $registry,
        Data $catalogData,
        CategoryProvider $categoryProvider,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->_catalogData = $catalogData;
        $this->_coreRegistry = $registry;
        parent::__construct($context, $data);
        $this->categoryProvider = $categoryProvider;
        $this->storeManager = $storeManager;
    }

    public function getCurrentCategory()
    {
        if (!$this->hasData('current_category')) {
            $this->setData('current_category', $this->_coreRegistry->registry('current_category'));
        }
        return $this->getData('current_category');
    }

    protected function _prepareLayout()
    {
        $tm = $this->getParentBlock();
        $category = $this->getCurrentCategory();

        if ($category) {
            $items = $this->getItemsForCategory($category);

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
        
        $dataLayerEvent = $this->getLayout()->getBlock('head.additional')->getData('datalayer_event');

        if (isset($dataLayerEvent['ecommerce']['items'])) {
            $items = $dataLayerEvent['ecommerce']['items'];
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
