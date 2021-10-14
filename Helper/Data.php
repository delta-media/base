<?php
namespace Dm\Base\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
	private $url;

	public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Catalog\Model\Product\Url $url
    ) {
    	$this->url = $url;

        parent::__construct($context);
    }

    public function getConfig($config_path)
    {
        return $this->scopeConfig->getValue(
            $config_path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function slug($string, $type = '')
    {
        if(!$string) {
            return '';
        }

        if($type == 'attr') {
            return str_replace('-', '_', $this->url->formatUrlKey($string));
        }

        if($type == 'img') {
            $path_parts = pathinfo($string);
            $string = $path_parts['filename'];
            $extension = $path_parts['extension'];
            return $this->url->formatUrlKey($string) . "." . strtolower($extension);
        }

        return $this->url->formatUrlKey($string);
    }

    public function getMediaUrl() {
        return $this->_urlBuilder->getBaseUrl(['_type' => \Magento\Framework\UrlInterface::URL_TYPE_MEDIA]);
    }
    
}
