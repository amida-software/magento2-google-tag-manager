<?php
namespace MagePal\GoogleTagManager\Block\Data;

use Magento\Catalog\Helper\Data;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use MagePal\GoogleTagManager\Block\DataLayer;
use MagePal\GoogleTagManager\DataLayer\CategoryData\CategoryProvider;
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

    protected function _toHtml()
    {
        $logger = new \Monolog\Logger('my-logger');
        $streamHandler = new \Monolog\Handler\StreamHandler(BP . '/var/log/test123.log', \Monolog\Logger::DEBUG);
        $logger->pushHandler($streamHandler);
        $logger->info('TEST');
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

            $parentBlock = $this->getParentBlock();
            if ($parentBlock) {
                $parentBlock->addCustomDataLayerByEvent(DataLayerEvent::CATEGORY_PAGE_EVENT, $data);
            }
        }

        return parent::_toHtml();
    }

    public function getCurrentCategory()
    {
        if (!$this->hasData('current_category')) {
            $this->setData('current_category', $this->_coreRegistry->registry('current_category'));
        }
        return $this->getData('current_category');
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
