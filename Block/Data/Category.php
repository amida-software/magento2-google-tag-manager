<?php
namespace MagePal\GoogleTagManager\Block\Data;

use Magento\Catalog\Helper\Data;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use MagePal\GoogleTagManager\DataLayer\CategoryData\CategoryProvider;
use MagePal\GoogleTagManager\Model\DataLayerEvent;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;

class Category extends Template
{
    protected $_catalogData = null;
    protected $_coreRegistry = null;
    private $categoryProvider;
    protected $storeManager;
    protected $session;

    public function __construct(
        Context $context,
        Registry $registry,
        Data $catalogData,
        CategoryProvider $categoryProvider,
        StoreManagerInterface $storeManager,
        SessionManagerInterface $session,
        array $data = []
    ) {
        $this->_catalogData = $catalogData;
        $this->_coreRegistry = $registry;
        parent::__construct($context, $data);
        $this->categoryProvider = $categoryProvider;
        $this->storeManager = $storeManager;
        $this->session = $session;
    }

    public function getCurrentCategory()
    {
        if (!$this->hasData('current_category')) {
            $this->setData('current_category', $this->_coreRegistry->registry('current_category'));
        }
        return $this->getData('current_category');
    }

    public function updateDataLayer($items, $currency, $totalValue)
    {
        $logger = new \Monolog\Logger('comp');
        $streamHandler = new \Monolog\Handler\StreamHandler(BP . '/var/log/dataLayer.log', \Monolog\Logger::DEBUG);
        $logger->pushHandler($streamHandler);
        $logger->info(__METHOD__);
        $tm = $this->getParentBlock();
        $category = $this->getCurrentCategory();

        if ($category) {
            $data = [
                'event' => DataLayerEvent::CATEGORY_PAGE_EVENT,
                'ecommerce' => [
                    'currency' => $currency,
                    'value' => $totalValue,
                    'items' => $items
                ]
            ];

            $tm->addCustomDataLayerByEvent(DataLayerEvent::CATEGORY_PAGE_EVENT, $data);
        }
    }

    protected function _prepareLayout()
    {
        return parent::_prepareLayout();
    }

    private function getItemsForCategory()
    {
        $items = [];
        if ($products = $this->session->getData('data_layer_category_list')) {
            $items = $products;
            $this->session->unsetData('data_layer_category_list');
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
