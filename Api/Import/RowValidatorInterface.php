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
namespace Smile\CustomEntityImportExport\Api\Import;

use Magento\Framework\Validator\ValidatorInterface;

/**
 * Row Validator Interface For Import Validation
 *
 * @category Smile
 * @package  Smile\CustomEntityImportExport\Api\Import
 * @author   Pierre Met <pierre.met@smile.fr>
 */
interface RowValidatorInterface extends ValidatorInterface
{
    const ERROR_INVALID_CUSTOM_ENTITY_NAME = 'invalidImportCustomEntityName';
    const ERROR_INVALID_ATTRIBUTE_SET_NAME = 'invalidImportAttributeSetName';

    /**
     * Value that means all entities (e.g. websites, groups etc.)
     */
    const VALUE_ALL = 'all';

    /**
     * Initialize validator
     *
     * @param \Smile\CustomEntityImportExport\Model\Import\CustomEntity $context Context
     *
     * @return $this
     */
    public function init($context);
}
