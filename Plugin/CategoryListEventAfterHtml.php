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

    public function afterToHtml(AbstractBlock $subject, $result)
    {
        $log = new \Monolog\Logger('comp');
        $log->pushHandler(new \Monolog\Handler\StreamHandler(BP . '/var/log/dataLayer.log', \Monolog\Logger::DEBUG));
        try {
            $fan = method_exists($subject, 'getRequest') ? $subject->getRequest()->getFullActionName() : null;

            // Только страница категории и только блок списка
            if ($fan !== 'catalog_category_view') {
                return $result;
            }
            if (!($subject instanceof ListProduct) && $subject->getNameInLayout() !== 'category.products.list') {
                return $result;
            }

            $category = $this->registry->registry('current_category');
            $items    = $this->session->getData('data_layer_category_list');

            $log->info(__METHOD__.' hit', [
                'nameInLayout' => $subject->getNameInLayout(),
                'class'        => get_class($subject),
                'hasCategory'  => (bool)$category,
                'itemsCount'   => is_array($items) ? count($items) : null,
            ]);

            if (!$category || empty($items) || !is_array($items)) {
                return $result;
            }

            $currency = $this->storeManager->getStore()->getCurrentCurrencyCode();
            $total = 0.0;
            foreach ($items as $i) {
                $total += (isset($i['price']) ? (float)$i['price'] : 0) * (isset($i['quantity']) ? (float)$i['quantity'] : 0);
            }
            $this->session->unsetData('data_layer_category_list');

            $payload = [
                'event' => DataLayerEvent::CATEGORY_PAGE_EVENT,
                'ecommerce' => [
                    'currency' => $currency,
                    'value'    => $total,
                    'items'    => $items,
                ],
            ];

            $json = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $script = '<script>window.dataLayer=window.dataLayer||[];window.dataLayer.push('.$json.');</script>';

            $log->info('pushed CATEGORY_PAGE_EVENT', ['bytes' => strlen($script)]);
            return $result . $script;

        } catch (\Throwable $e) {
            $log->error('err: '.$e->getMessage());
            return $result;
        }
    }
}
