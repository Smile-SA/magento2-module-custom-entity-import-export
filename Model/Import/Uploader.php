<?php
/**
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile\CustomEntityImportExport
 * @author    Pierre Met <pierre.met@smile.fr>
 * @copyright 2019 Smile
 * @license   Open Software License ("OSL") v. 3.0
 */
namespace Smile\CustomEntityImportExport\Model\Import;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\DriverPool;
use Magento\MediaStorage\Helper\File\Storage\Database;
use Magento\MediaStorage\Helper\File\Storage;
use Magento\Framework\Image\AdapterFactory;
use Magento\MediaStorage\Model\File\Validator\NotProtectedExtension;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\File\ReadFactory;

/**
 * File Uploader
 *
 * @category Smile
 * @package  Smile\CustomEntityImportExport\Model\Import
 * @author   Pierre Met <pierre.met@smile.fr>
 */
class Uploader extends \Magento\CatalogImportExport\Model\Import\Uploader
{

    /**
     * HTTP scheme
     * used to compare against the filename and select the proper DriverPool adapter
     * @var string
     */
    private $httpScheme = 'http://';

    /**
     * Uploader constructor.
     *
     * @param Database              $coreFileStorageDb Database
     * @param Storage               $coreFileStorage   Storage
     * @param AdapterFactory        $imageFactory      AdapterFactory
     * @param NotProtectedExtension $validator         NotProtectedExtension
     * @param Filesystem            $filesystem        Filesystem
     * @param ReadFactory           $readFactory       ReadFactory
     * @param string                $filePath          File Path
     *
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        Database $coreFileStorageDb,
        Storage $coreFileStorage,
        AdapterFactory $imageFactory,
        NotProtectedExtension $validator,
        Filesystem $filesystem,
        ReadFactory $readFactory,
        $filePath = null
    ) {
        parent::__construct(
            $coreFileStorageDb,
            $coreFileStorage,
            $imageFactory,
            $validator,
            $filesystem,
            $readFactory,
            $filePath
        );
    }

    /**
     * Proceed moving a file from TMP to destination folder
     *
     * @param string $fileName      File Name
     * @param bool   $renameFileOff Rename File Off
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     *
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function move($fileName, $renameFileOff = false)
    {
        if ($renameFileOff) {
            $this->setAllowRenameFiles(false);
        }

        $filePath = '';
        if ($this->getTmpDir()) {
            $filePath = $this->getTmpDir() . DIRECTORY_SEPARATOR;
        }

        if (preg_match('/\bhttps?:\/\//i', $fileName, $matches)) {
            $url = str_replace($matches[0], '', $fileName);
            $driver = $matches[0] === $this->httpScheme ? DriverPool::HTTP : DriverPool::HTTPS;
            $read = $this->_readFactory->create($url, $driver);

            $parsedUrlPath = parse_url($url, PHP_URL_PATH);
            if ($parsedUrlPath) {
                $urlPathValues = explode(DIRECTORY_SEPARATOR, $parsedUrlPath);
                if (!empty($urlPathValues)) {
                    $fileName = end($urlPathValues);
                }
            }

            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
            if ($fileExtension && !$this->checkAllowedExtension($fileExtension)) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Disallowed file type.'));
            }

            $fileName = preg_replace('/[^a-z0-9\._-]+/i', '', $fileName);
            $this->_directory->writeFile(
                $this->_directory->getRelativePath($filePath . $fileName),
                $read->readAll()
            );
        }

        $filePath = $this->_directory->getRelativePath($filePath . $fileName);
        $this->_setUploadFile($filePath);
        $destDir = $this->_directory->getAbsolutePath($this->getDestDir());
        $result = $this->save($destDir);
        unset($result['path']);
        $result['name'] = self::getCorrectFileName($result['name']);

        return $result;
    }
}
