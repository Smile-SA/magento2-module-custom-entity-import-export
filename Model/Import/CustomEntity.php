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

use Smile\CustomEntityImportExport\Api\Import\RowValidatorInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\StringUtils;
use Magento\ImportExport\Model\Import as MagentoImport;
use Magento\ImportExport\Model\Import\AbstractEntity;
use Magento\ImportExport\Model\ImportFactory;
use Magento\ImportExport\Model\ResourceModel\Helper;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Psr\Log\LoggerInterface;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory;
use Smile\CustomEntity\Api\CustomEntityRepositoryInterface;
use Smile\CustomEntity\Api\Data\CustomEntityInterfaceFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Catalog\Model\Category\FileInfo;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\ImportExport\Model\Import;
use Magento\Framework\Exception\LocalizedException;
use Smile\CustomEntityImportExport\Model\Import\UploaderFactory;
use Magento\Eav\Model\Config;

/**
 * Custom Entity Import Class
 *
 * @category Smile
 * @package  Smile\CustomEntityImportExport\Model\Import
 * @author   Pierre Met <pierre.met@smile.fr>
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CustomEntity extends AbstractEntity
{
    const ENTITY_TYPE = 'smile_custom_entity';

    const NAME = 'name';
    const IS_ACTIVE = 'is_active';
    const ATTRIBUTE_SET = 'attribute_set';
    const STORE_ID = 'store_id';
    const DESTINATION_DIR = 'scoped_eav/entity';

    /** @var array */
    protected $_permanentAttributes = [
        self::NAME,
        self::IS_ACTIVE,
        self::ATTRIBUTE_SET,
    ];

    /** @var LoggerInterface  */
    protected $logger;

    /** @var CollectionFactory */
    protected $attributeSetCollectionFactory;

    /** @var CustomEntityRepositoryInterface */
    protected $entityRepositoryInterface;

    /** @var CustomEntityInterfaceFactory */
    protected $customEntityFactory;

    /** @var SearchCriteriaBuilder */
    protected $searchCriteria;

    /** @var array */
    protected $attributeSets;

    /** @var array */
    protected $customEntities;

    /** @var FileInfo */
    protected $importFile;

    /** @var Filesystem */
    protected $filesystem;

    /** @var \Smile\CustomEntityImportExport\Model\Import\Uploader */
    protected $fileUploader;

    /** @var UploaderFactory */
    protected $uploaderFactory;

    /** @var \Magento\Framework\Filesystem\Directory\WriteInterface */
    protected $mediaDirectory;

    /** @var Config */
    protected $eavConfig;

    /** @var \Smile\CustomEntity\Model\ResourceModel\CustomEntity\Attribute\CollectionFactory */
    protected $customEntityCollectionFactory;

    /** @var array */
    protected $attributeCodesInSet = [];

    /**
     * CustomEntity constructor.
     *
     * @param StringUtils                        $string                        StringUtils
     * @param ScopeConfigInterface               $scopeConfig                   ScopeConfigInterface
     * @param ImportFactory                      $importFactory                 ImportFactory
     * @param Helper                             $resourceHelper                Helper
     * @param ResourceConnection                 $resource                      ResourceConnection
     * @param ProcessingErrorAggregatorInterface $errorAggregator               ProcessingErrorAggregatorInterface
     * @param LoggerInterface                    $logger                        LoggerInterface
     * @param CollectionFactory                  $attributeSetCollectionFactory CollectionFactory
     * @param CustomEntityRepositoryInterface    $entityRepositoryInterface     CustomEntityRepositoryInterface
     * @param CustomEntityInterfaceFactory       $customEntityFactory           CustomEntityInterfaceFactory
     * @param SearchCriteriaBuilder              $searchCriteria                SearchCriteriaBuilder
     * @param FileInfo                           $importFile                    FileInfo
     * @param Filesystem                         $filesystem                    Filesystem
     * @param UploaderFactory                    $uploaderFactory               UploaderFactory
     * @param Config                             $eavConfig                     Eav Config
     * @param array                              $data                          Data
     *
     * @throws \Magento\Framework\Exception\FileSystemException
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        StringUtils $string,
        ScopeConfigInterface $scopeConfig,
        ImportFactory $importFactory,
        Helper $resourceHelper,
        ResourceConnection $resource,
        ProcessingErrorAggregatorInterface $errorAggregator,
        LoggerInterface $logger,
        CollectionFactory $attributeSetCollectionFactory,
        CustomEntityRepositoryInterface $entityRepositoryInterface,
        CustomEntityInterfaceFactory $customEntityFactory,
        \Smile\CustomEntity\Model\ResourceModel\CustomEntity\Attribute\CollectionFactory $customEntityCollectionFactory,
        SearchCriteriaBuilder $searchCriteria,
        FileInfo $importFile,
        FileSystem $filesystem,
        UploaderFactory $uploaderFactory,
        Config $eavConfig,
        array $data = []
    ) {
        parent::__construct($string, $scopeConfig, $importFactory, $resourceHelper, $resource, $errorAggregator, $data);

        $this->_availableBehaviors = [
            MagentoImport::BEHAVIOR_ADD_UPDATE,
        ];
        $this->entityRepositoryInterface = $entityRepositoryInterface;
        $this->customEntityFactory = $customEntityFactory;
        $this->searchCriteria = $searchCriteria;
        $this->logger = $logger;
        $this->attributeSetCollectionFactory = $attributeSetCollectionFactory;
        $this->importFile = $importFile;
        $this->filesystem = $filesystem;
        $this->uploaderFactory = $uploaderFactory;
        $this->eavConfig = $eavConfig;
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::ROOT);
        $this->masterAttributeCode = self::NAME;
        $this->customEntityCollectionFactory = $customEntityCollectionFactory;
    }

    /**
     * Validate each row
     *
     * @param array $rowData Row Data
     * @param int   $rowNum  Row Num
     *
     * @return bool
     *
     * @throws LocalizedException
     */
    public function validateRow(array $rowData, $rowNum)
    {
        if (isset($this->_validatedRows[$rowNum])) {
            return !$this->getErrorAggregator()->isRowInvalid($rowNum);
        }

        $name = $rowData[self::NAME];
        if (trim($name) == '' || $name == null) {
            $this->addRowError(RowValidatorInterface::ERROR_INVALID_CUSTOM_ENTITY_NAME, $rowNum);

            return false;
        }

        $attr = $rowData[self::ATTRIBUTE_SET];
        if (trim($attr) == '' || $attr == null || !$this->getAttributeSetId($attr)) {
            $this->addRowError(RowValidatorInterface::ERROR_INVALID_ATTRIBUTE_SET_NAME, $rowNum);

            return false;
        }

        $this->_validatedRows[$rowNum] = true;

        return !$this->getErrorAggregator()->isRowInvalid($rowNum);
    }

    /**
     * Get Entity type code
     *
     * @return string
     */
    public function getEntityTypeCode()
    {
        return self::ENTITY_TYPE;
    }

    /**
     * Save each Custom Entities
     *
     * @return void
     *
     * @throws \Exception
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function saveCustomEntities()
    {
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            foreach ($bunch as $rowNum => $rowData) {
                try {
                    if (!$this->validateRow($rowData, $rowNum)) {
                        continue;
                    }
                    if ($this->getErrorAggregator()->hasToBeTerminated()) {
                        $this->getErrorAggregator()->addRowToSkip($rowNum);
                        continue;
                    }

                    $storeId = (empty($rowData[self::STORE_ID])) ? 0 : $rowData[self::STORE_ID];

                    /** @var \Smile\CustomEntity\Api\Data\CustomEntityInterface $custom */
                    $custom = $this->customEntityFactory->create();
                    $custom->setName($rowData[self::NAME]);
                    $custom->setIsActive((bool) $rowData[self::IS_ACTIVE]);
                    $custom->setStoreId((int) $storeId);
                    $setId = $this->getAttributeSetId($rowData[self::ATTRIBUTE_SET]);
                    $custom->setAttributeSetId($setId);

                    foreach ($this->getAttributeCodesInSet($setId) as $code => $frontendInput)
                    {
                        if (
                            $code == self::NAME
                            || $code == self::IS_ACTIVE
                            || $code == self::STORE_ID
                            || !isset($rowData[$code])
                        ) continue;
                        $value = $rowData[$code];
                        if ($frontendInput == 'image') {
                            $uploadedFile = $this->uploadMediaFiles($value);
                            $uploadedFile = $uploadedFile ?: $this->getSystemFile($value);
                            $uploadedFile = DIRECTORY_SEPARATOR . DirectoryList::MEDIA . DIRECTORY_SEPARATOR . self::DESTINATION_DIR . DIRECTORY_SEPARATOR . $uploadedFile;
                            if ($uploadedFile) {
                                $value = $uploadedFile;
                            }
                        }
                        $custom->setData($code, $value);
                    }

                    $existCustomEntity = $this->existCustomEntity($rowData[self::NAME], $rowData[self::ATTRIBUTE_SET]);
                    if ($existCustomEntity) {
                        $custom->setId($existCustomEntity);
                    }

                    $this->entityRepositoryInterface->save($custom);
                    $this->customEntities[$custom->getName().'_'.$custom->getAttributeSetId()] = $custom->getId();
                } catch (\Exception $e) {
                    $this->logger->error($e->getMessage(), $rowData);
                    throw new \Exception($e);
                }
            }
        }
    }

    public function getAttributeCodesInSet($setId)
    {
        if (!isset($this->attributeCodesInSet[$setId])) {
            $attributesCollection = $this->customEntityCollectionFactory->create()
                ->setAttributeSetFilter($setId);
            foreach ($attributesCollection as $attribute) {
                $this->attributeCodesInSet[$setId][$attribute->getAttributeCode()] = $attribute->getFrontendInput();
            }
        }

        return $this->attributeCodesInSet[$setId];
    }

    /**
     * Get Attribute Set Id by Attribute Set Name
     *
     * @param string $attributeSetName Attribute Set Name
     *
     * @return bool|int
     *
     * @throws LocalizedException
     */
    public function getAttributeSetId($attributeSetName)
    {
        if (array_key_exists($attributeSetName, $this->getAllAttributeSet())) {
            return $this->getAllAttributeSet()[$attributeSetName];
        }

        return false;
    }

    /**
     * Get All AttributeSet
     *
     * @return array
     *
     * @throws LocalizedException
     */
    public function getAllAttributeSet()
    {
        if ($this->attributeSets == null) {
            $attributeSetCollection = $this->attributeSetCollectionFactory->create()
                ->addFieldToSelect(['attribute_set_name', 'attribute_set_id'])
                ->setEntityTypeFilter($this->getEntityTypeId('smile_custom_entity'))
                ->getItems();
            $this->attributeSets = [];
            foreach ($attributeSetCollection as $attributeSet) {
                $this->attributeSets[$attributeSet->getAttributeSetName()] = $attributeSet->getAttributeSetId();
            }
        }

        return $this->attributeSets;
    }

    /**
     * Get Entity Type Id by Entity Type Name
     *
     * @param string $entityTypeName entityTypeName
     *
     * @return string|null
     *
     * @throws LocalizedException
     */
    public function getEntityTypeId($entityTypeName)
    {
        return $this->eavConfig->getEntityType($entityTypeName)->getEntityTypeId();
    }

    /**
     * Get Custom Entities Collection
     *
     * @return array
     */
    public function getCustomEntitiesCollection()
    {
        if ($this->customEntities == null) {
            $searchCriteria = $this->searchCriteria->create();
            $customEntitiesCol = $this->entityRepositoryInterface->getList($searchCriteria)->getItems();
            $this->customEntities = [];
            foreach ($customEntitiesCol as $entity) {
                $this->customEntities[$entity->getName().'_'.$entity->getAttributeSetId()] = $entity->getId();
            }
        }

        return $this->customEntities;
    }

    /**
     * Get Exist Custom Entity
     *
     * @param string $name          Name
     * @param string $attributeName Attribute Name
     *
     * @return bool|int
     *
     * @throws LocalizedException
     */
    public function existCustomEntity($name, $attributeName)
    {
        if (array_key_exists(
            $name.'_'.$this->getAttributeSetId($attributeName),
            $this->getCustomEntitiesCollection()
        )) {
            return $this->getCustomEntitiesCollection()[$name.'_'.$this->getAttributeSetId($attributeName)];
        }

        return false;
    }

    /**
     * Get Directory List
     *
     * @return array
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    protected function getDirectoryList()
    {
        return DirectoryList::getDefaultConfig();
    }

    /**
     * Get Uploader
     *
     * @return Uploader
     *
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    protected function getUploader()
    {
        if ($this->fileUploader === null) {
            $this->fileUploader = $this->uploaderFactory->create();

            $this->fileUploader->init();

            $dirAddon = $this->getDirectoryList()[DirectoryList::MEDIA][DirectoryList::PATH];

            $tmpPath = $dirAddon . DIRECTORY_SEPARATOR . $this->mediaDirectory->getRelativePath('import');
            if (!empty($this->_parameters[Import::FIELD_NAME_IMG_FILE_DIR])) {
                $tmpPath = $this->_parameters[Import::FIELD_NAME_IMG_FILE_DIR];
            }

            if (!$this->fileUploader->setTmpDir($tmpPath)) {
                throw new LocalizedException(
                    __('File directory \'%1\' is not readable.', $tmpPath)
                );
            }
            $destinationPath = $dirAddon.DIRECTORY_SEPARATOR.$this->mediaDirectory->getRelativePath(self::DESTINATION_DIR);

            $this->mediaDirectory->create($destinationPath);
            if (!$this->fileUploader->setDestDir($destinationPath)) {
                throw new LocalizedException(
                    __('File directory \'%1\' is not writable.', $destinationPath)
                );
            }
        }

        $this->fileUploader->setFilesDispersion(false);

        return $this->fileUploader;
    }

    /**
     * Upload Media Files
     *
     * @param string $filename      filename
     * @param bool   $renameFileOff renameFileOff
     *
     * @return string
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    protected function uploadMediaFiles($filename, $renameFileOff = false)
    {
        $result = '';
        try {
            $res = $this->getUploader()->move($filename, $renameFileOff);
            $result = $res['file'];
        } catch (\Exception $e) {
            $this->logger->info($e);
        }

        return $result;
    }

    /**
     * Try to find file by it's path.
     *
     * @param string $filename filename
     * @return string
     */
    protected function getSystemFile($filename)
    {
        $filePath = self::DESTINATION_DIR . DIRECTORY_SEPARATOR . $filename;
        /** @var \Magento\Framework\Filesystem\Directory\ReadInterface $read */
        $read = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);

        return $read->isExist($filePath) && $read->isReadable($filePath) ? $filename : '';
    }

    /**
     * Import data
     *
     * @return bool
     * @throws \Exception
     */
    protected function _importData()
    {
        $this->saveCustomEntities();
        $this->_validatedRows = null;

        return true;
    }
}
