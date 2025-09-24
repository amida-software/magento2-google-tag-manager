<?php
namespace MagePal\GoogleTagManager\Plugin;

use Magento\Catalog\Block\Product\ListProduct;
use Magento\Framework\Registry;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use MagePal\GoogleTagManager\Model\DataLayerEvent;

class CategoryListEventAfterHtml
{
    public function __construct(
        private Registry $registry,
        private StoreManagerInterface $storeManager,
        private SessionManagerInterface $session
    ) {}

    public function afterToHtml(ListProduct $subject, $result)
    {
        $logger = new \Monolog\Logger('comp');
        $logger->pushHandler(new \Monolog\Handler\StreamHandler(BP . '/var/log/dataLayer.log', \Monolog\Logger::DEBUG));

        try {
            $logger->info(__METHOD__ . ' start', [
                'fullAction'   => method_exists($subject, 'getRequest') ? $subject->getRequest()->getFullActionName() : null,
                'nameInLayout' => method_exists($subject, 'getNameInLayout') ? $subject->getNameInLayout() : null,
            ]);

            $category = $this->registry->registry('current_category');
            $items    = $this->session->getData('data_layer_category_list');

            $logger->info('state', [
                'hasCategory' => (bool)$category,
                'itemsType'   => gettype($items),
                'itemsCount'  => is_array($items) ? count($items) : null,
            ]);

            if (!$category || empty($items) || !is_array($items)) {
                $logger->info('skip', ['reason' => !$category ? 'no_category' : 'no_items']);
                return $result;
            }

            $logger->info('category', [
                'id'   => method_exists($category, 'getId') ? $category->getId() : null,
                'name' => method_exists($category, 'getName') ? $category->getName() : null,
            ]);

            $first = reset($items);
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
            $logger->info('payload_ready', [
                'itemsCount' => count($items),
                'hasValue'   => isset($payload['ecommerce']['value']),
            ]);

            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $script = '<script>window.dataLayer=window.dataLayer||[];window.dataLayer.push(' . $json . ');</script>';
            $logger->info('appended_script', ['bytes' => strlen($script)]);
        } catch (\Throwable $e) {
            $logger->error(__METHOD__ . ' error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }

        return $result . (isset($script) ? $script : '');
    }

}
