<?php
namespace MagePal\GoogleTagManager\Plugin;

use Magento\Framework\View\Element\AbstractBlock;
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

    public function afterToHtml(\Magento\Framework\View\Element\AbstractBlock $subject, $result)
    {
        $log = new \Monolog\Logger('comp');
        $log->pushHandler(new \Monolog\Handler\StreamHandler(BP . '/var/log/dataLayer.log', \Monolog\Logger::DEBUG));

        try {
            $fan   = method_exists($subject, 'getRequest') ? $subject->getRequest()->getFullActionName() : null;
            $name  = method_exists($subject, 'getNameInLayout') ? $subject->getNameInLayout() : null;
            $class = get_class($subject);

            $log->info('CategoryListEventAfterHtml::afterToHtml ENTRY', [
                'fullAction'   => $fan,
                'nameInLayout' => $name,
                'class'        => $class,
            ]);

            if ($fan !== 'catalog_category_view') {
                $log->info('EXIT: not category page');
                return $result;
            }

            $isList = ($subject instanceof \Magento\Catalog\Block\Product\ListProduct)
                || in_array((string)$name, ['category.products.list','category.products','product_list','search_result_list'], true);

            if (!$isList) {
                $log->info('EXIT: not product list', ['nameInLayout' => $name, 'class' => $class]);
                return $result;
            }

            $category = $this->registry->registry('current_category');
            $items    = $this->session->getData('data_layer_category_list');

            $log->info('STATE', [
                'hasCategory' => (bool)$category,
                'itemsType'   => gettype($items),
                'itemsCount'  => is_array($items) ? count($items) : null,
            ]);

            if (!$category || empty($items) || !is_array($items)) {
                $log->info('EXIT: no data', ['reason' => !$category ? 'no_category' : 'no_items']);
                return $result;
            }

            $currency = $this->storeManager->getStore()->getCurrentCurrencyCode();
            $total = 0.0;
            foreach ($items as $i) {
                $total += (isset($i['price']) ? (float)$i['price'] : 0) * (isset($i['quantity']) ? (float)$i['quantity'] : 0);
            }
            $this->session->unsetData('data_layer_category_list');

            $payload = [
                'event' => \MagePal\GoogleTagManager\Model\DataLayerEvent::CATEGORY_PAGE_EVENT,
                'ecommerce' => [
                    'currency' => $currency,
                    'value'    => $total,
                    'items'    => $items,
                ],
            ];

            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $script = '<script>window.dataLayer=window.dataLayer||[];window.dataLayer.push(' . $json . ');</script>';

            $log->info('PUSHED CATEGORY_PAGE_EVENT', ['bytes' => strlen($script)]);
            return $result . $script;

        } catch (\Throwable $e) {
            $log->error('ERROR: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $result;
        }
    }

}
