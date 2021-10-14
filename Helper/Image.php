<?php
namespace Dm\Base\Helper;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\UrlInterface;
use Kint;

class Image extends \Magento\Framework\App\Helper\AbstractHelper
{
    const PATH_RESIZED = 'resized/';
	private $storeManager;
    private $imageHelper;
    private $filesystem;
    private $directory;
    private $imageFactory;
    private $productRepository;

	public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\Image\AdapterFactory $imageFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    ) {
        $this->storeManager = $storeManager;
    	$this->imageHelper = $imageHelper;
        $this->filesystem = $filesystem;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->imageFactory = $imageFactory;
        $this->productRepository = $productRepository;

        parent::__construct($context);
    }

    public function getColorFromImage($image)
    {
        $placeholder = $this->getResizeImage($image, 10, 'product', 0, true);

        $newFile = $placeholder;

        $extension = strtolower(pathinfo($newFile, PATHINFO_EXTENSION));

        try {
	        if ($extension == 'jpg' || $extension == 'jpeg') {
	            $color = $this->averageColor(imagecreatefromjpeg($newFile));
	        } else if ($extension == 'png') {
	            $color = $this->averageColor(imagecreatefrompng($newFile));
	        } else {
	            $color = '#ffffff';
	        }
        } catch (\Throwable $e) {
			$color = '#ffffff';
        }

        if ($color == '#000000') $color = '#ffffff';

        return $color;
    }

    public function getColorFromProductImage($product)
    {
        return $this->getColorFromImage($product->getSmallImage());
    }

    public function averageColor($img) {
        $w = imagesx($img);
        $h = imagesy($img);
        $r = $g = $b = 0;
        for($y = 0; $y < $h; $y++) {
            for($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($img, $x, $y);
                $r += $rgb >> 16;
                $g += $rgb >> 8 & 255;
                $b += $rgb & 255;
            }
        }
        $pxls = $w * $h;
        $r = dechex(round($r / $pxls));
        $g = dechex(round($g / $pxls));
        $b = dechex(round($b / $pxls));
        if(strlen($r) < 2) {
            $r = 0 . $r;
        }
        if(strlen($g) < 2) {
            $g = 0 . $g;
        }
        if(strlen($b) < 2) {
            $b = 0 . $b;
        }

        return "#" . $r . $g . $b;
    }

    public function getHoverImage($product, $size = 300)
    {
        $product = $this->productRepository->get($product->getSku());

        $images = $product->getMediaGalleryImages();
        $hoverImage = false;
        foreach ($images as $image) {
            $fileExtension = pathinfo($image->getFile(), PATHINFO_EXTENSION);
            if(strtolower($fileExtension) == 'gif') {
                $hoverImage = $image;
                break;
            }
            if($image->getPosition() != 1 && strpos($image->getFile(), '_1') !== false && strpos($image->getFile(), 'hqdefault') == false) {
                $hoverImage = $image;
                break;
            }
        }
        if($hoverImage) {
            $imageUrl = $this->imageHelper->init($product, 'category_page_grid')->setImageFile($hoverImage->getFile())->constrainOnly(FALSE)->keepAspectRatio(TRUE)->keepFrame(FALSE)->resize($size)->getUrl();
            // $imageUrl = $this->getResizeImage($hoverImage->getFile());
            return $imageUrl;
        }

        return false;
    }

    public function getResizeImage($imageName, $size = 300, $entity = 'product', $quality = false, $file = false)
    {
        $path = 'catalog/product/';
        if($entity == 'category') {
            $path = 'catalog/category/';
        }

        $realPath = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath($path . $imageName);
        if (!$this->directory->isFile($realPath) || !$this->directory->isExist($realPath)) {
            return false;
        }

        $basename = pathinfo($realPath, PATHINFO_BASENAME);

        $targetDir = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath(self::PATH_RESIZED . $size . '/' . $basename[0] . '/' . $basename[1]);
        $pathTargetDir = $this->directory->getRelativePath($targetDir);

        if (!$this->directory->isExist($pathTargetDir)) {
            $this->directory->create($pathTargetDir);
        }
        if (!$this->directory->isExist($pathTargetDir)) {
            return false;
        }

        $image = $this->imageFactory->create();
        $image->open($realPath);
        $image->keepAspectRatio(true);
        $image->resize($size);
        if($quality !== false) {
            $image->quality($quality);
        }
        $dest = $targetDir . '/' . $basename;
        $image->save($dest);

        if($file) {
            return $dest;
        }
        else {
            if ($this->directory->isFile($this->directory->getRelativePath($dest))) {
                return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . self::PATH_RESIZED . $size . $imageName;
            }
        }
        return false;
    }
}
