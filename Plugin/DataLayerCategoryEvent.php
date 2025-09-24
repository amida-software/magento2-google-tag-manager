<?php


namespace MagePal\GoogleTagManager\Plugin;

use MagePal\GoogleTagManager\Model\DataLayerEvent;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;

class DataLayerCategoryEvent
{
    public function __construct(
        private Registry                $registry,
        private StoreManagerInterface   $storeManager,
        private SessionManagerInterface $session
    )
    {
    }

    public function afterToHtml(\MagePal\GoogleTagManager\Block\DataLayer $subject, $result)
    {
        $logger = new \Monolog\Logger('comp');
        $logger->pushHandler(new \Monolog\Handler\StreamHandler(BP . '/var/log/dataLayer.log', \Monolog\Logger::DEBUG));

        try {
            $logger->info(__METHOD__ . ' start', [
                'fullAction'   => method_exists($subject, 'getRequest') ? $subject->getRequest()->getFullActionName() : null,
                'subjectClass' => get_class($subject),
                'subjectName'  => method_exists($subject, 'getNameInLayout') ? $subject->getNameInLayout() : null,
            ]);

            $category = $this->registry->registry('current_category');
            $items    = $this->session->getData('data_layer_category_list');

            $logger->info('state', [
                'hasCategory' => (bool)$category,
                'itemsType'   => gettype($items),
                'itemsCount'  => is_array($items) ? count($items) : null,
            ]);

            if (!$category || empty($items) || !is_array($items)) {
                $logger->info('skip', [
                    'reason' => !$category ? 'no_category' : 'no_items',
                ]);
                return $result;
            }

            $first = reset($items);
            $logger->info('category', [
                'id'   => method_exists($category, 'getId') ? $category->getId() : null,
                'name' => method_exists($category, 'getName') ? $category->getName() : null,
            ]);
            $logger->info('items_sample', [
                'keys'  => is_array($first) ? array_slice(array_keys($first), 0, 12) : null,
                'first' => is_array($first) ? array_intersect_key($first, array_flip(['item_id','id','sku','item_name','name','price','quantity','index'])) : null,
            ]);

            $currency = $this->storeManager->getStore()->getCurrentCurrencyCode();
            $total = 0.0;
            foreach ($items as $i) {
                $total += (isset($i['price']) ? (float)$i['price'] : 0) * (isset($i['quantity']) ? (float)$i['quantity'] : 0);
            }
            $logger->info('calc', ['currency' => $currency, 'total' => $total]);

            $this->session->unsetData('data_layer_category_list');

            $payload = [
                'event' => DataLayerEvent::CATEGORY_PAGE_EVENT,
                'ecommerce' => [
                    'currency' => $currency,
                    'value'    => $total,
                    'items'    => $items,
                ],
            ];

            $hasACDLBE = method_exists($subject, 'addCustomDataLayerByEvent');
            $hasACDL   = method_exists($subject, 'addCustomDataLayer');
            $hasAV     = method_exists($subject, 'addVariable');

            $logger->info('subject_methods', [
                'addCustomDataLayerByEvent' => $hasACDLBE,
                'addCustomDataLayer'        => $hasACDL,
                'addVariable'               => $hasAV,
            ]);

            if ($hasACDLBE) {
                $subject->addCustomDataLayerByEvent(DataLayerEvent::CATEGORY_PAGE_EVENT, $payload);
                $logger->info('pushed', ['via' => 'addCustomDataLayerByEvent']);
            } elseif ($hasACDL) {
                $subject->addCustomDataLayer($payload);
                $logger->info('pushed', ['via' => 'addCustomDataLayer']);
            } elseif ($hasAV) {
                $subject->addVariable('event', $payload['event']);
                $subject->addVariable('ecommerce', $payload['ecommerce']);
                $logger->info('pushed', ['via' => 'addVariable']);
            } else {
                $existing = (array)($subject->getData('custom_datalayer') ?? []);
                $subject->setData('custom_datalayer', array_merge($existing, [$payload]));
                $logger->info('pushed', ['via' => 'setData(custom_datalayer)']);
            }
        } catch (\Throwable $e) {
            $logger->error(__METHOD__ . ' error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }

        return $result;
    }

}

