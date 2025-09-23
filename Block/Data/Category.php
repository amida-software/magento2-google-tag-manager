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

        try {
            $logger = new \Monolog\Logger('gtm');
            $logger->pushHandler(new \Monolog\Handler\StreamHandler(BP . '/var/log/dataLayer.log', \Monolog\Logger::DEBUG));
            $logger->info(__METHOD__, [
                'fullAction'   => $context->getRequest()->getFullActionName(),
                'nameInLayout' => $this->getNameInLayout(),
                'isAjax'       => $context->getRequest()->isAjax(),
            ]);
        } catch (\Throwable $e) {}
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
        $logger = new \Monolog\Logger('gtm');
        $streamHandler = new \Monolog\Handler\StreamHandler(BP . '/var/log/dataLayer.log', \Monolog\Logger::DEBUG);
        $logger->pushHandler($streamHandler);
        $logger->info(__METHOD__, [
            'items_cnt' => is_array($items) ? count($items) : 0,
            'currency'  => $currency,
            'total'     => $totalValue
        ]);

        $tm = $this->getParentBlock() ?: $this->getLayout()->getBlock('magepal_gtm_datalayer');
        $category = $this->getCurrentCategory();

        if (!$tm || !$category || empty($items)) {
            $logger->info('Skip push', ['has_tm' => (bool)$tm, 'has_cat' => (bool)$category, 'has_items' => !empty($items)]);
            return;
        }

        $payload = [
            'event' => DataLayerEvent::CATEGORY_PAGE_EVENT,
            'ecommerce' => [
                'currency' => $currency,
                'value'    => $totalValue,
                'items'    => $items
            ]
        ];

        if (method_exists($tm, 'addCustomDataLayerByEvent')) {
            $tm->addCustomDataLayerByEvent(DataLayerEvent::CATEGORY_PAGE_EVENT, $payload);
            $logger->info('Pushed via addCustomDataLayerByEvent');
        } elseif (method_exists($tm, 'addCustomDataLayer')) {
            $tm->addCustomDataLayer($payload);
            $logger->info('Pushed via addCustomDataLayer');
        } elseif (method_exists($tm, 'addVariable')) {
            $tm->addVariable('event', $payload['event']);
            $tm->addVariable('ecommerce', $payload['ecommerce']);
            $logger->info('Pushed via addVariable');
        } else {
            $existing = (array)($tm->getData('custom_datalayer') ?? []);
            $tm->setData('custom_datalayer', array_merge($existing, [$payload]));
            $logger->info('Pushed via setData(custom_datalayer)');
        }
    }

    protected function _prepareLayout()
    {
        $logger = new \Monolog\Logger('gtm');
        $logger->pushHandler(new \Monolog\Handler\StreamHandler(BP . '/var/log/dataLayer.log', \Monolog\Logger::DEBUG));

        try {
            $logger->info(__METHOD__ . ' start', ['nameInLayout' => $this->getNameInLayout()]);
            $items = $this->getItemsForCategory();
            if (empty($items)) {
                $logger->info('No items in session data_layer_category_list');
                return parent::_prepareLayout();
            }
            $currency   = $this->getCurrencyName();
            $totalValue = $this->calculateTotalValue($items);
            $this->updateDataLayer($items, $currency, $totalValue);
        } catch (\Throwable $e) {
            $logger->error('Exception: ' . $e->getMessage());
        }

        return parent::_prepareLayout();
    }

    private function getItemsForCategory()
    {
        $items = [];
        $products = $this->session->getData('data_layer_category_list');
        if (is_array($products) && !empty($products)) {
            $items = $products;
        }
        $this->session->unsetData('data_layer_category_list');
        return $items;
    }

    public function getCategoryPath()
    {
        $titleArray = [];
        $breadCrumbs = $this->_catalogData->getBreadcrumbPath();
        foreach ($breadCrumbs as $breadCrumb) {
            $titleArray[] = $breadCrumb['label'];
        }
        return implode(' > ', $titleArray);
    }

    protected function getCurrencyName()
    {
        return $this->storeManager->getStore()->getCurrentCurrencyCode();
    }

    private function calculateTotalValue($items)
    {
        $totalValue = 0.0;
        foreach ((array)$items as $item) {
            $price = isset($item['price']) ? (float)$item['price'] : 0.0;
            $qty   = isset($item['quantity']) ? (float)$item['quantity'] : 0.0;
            $totalValue += $price * $qty;
        }
        return $totalValue;
    }
}
