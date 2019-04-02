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
namespace Smile\CustomEntityImportExport\Model\Source\Import\Behavior;

use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Source\Import\AbstractBehavior;

/**
 * Import Option Add/Update Basic
 *
 * @category Smile
 * @package  Smile\CustomEntityImportExport\Model\Source\Import\Behavior
 * @author   Pierre Met <pierre.met@smile.fr>
 */
class AddUpdateBasic extends AbstractBehavior
{
    const IMPORT_BEHAVIOR_CODE = 'smile_add_update_basic';

    /**
     * Get Options
     *
     * @return array
     */
    public function toArray()
    {
        $this;

        return [
            Import::BEHAVIOR_ADD_UPDATE => __('Import/Update'),
        ];
    }

    /**
     * Get Code
     *
     * @return string
     */
    public function getCode()
    {
        return self::IMPORT_BEHAVIOR_CODE;
    }
}
