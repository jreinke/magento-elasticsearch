<?php
/**
 * Class that will require catalog search reindexation after search engine choice in config.
 *
 * @package JR_Search
 * @subpackage JR_Search_Model
 * @author Johann Reinke <johann.reinke@gmail.com>
 */
class JR_Search_Model_Adminhtml_System_Config_Backend_Engine extends Mage_Core_Model_Config_Data
{
    /**
     * Requires catalog category products and catalog search reindexation.
     *
     * @return JR_Search_Model_Adminhtml_System_Config_Backend_Engine
     */
    protected function _afterSave()
    {
        $indexer = Mage::getSingleton('index/indexer');
        $indexer->getProcessByCode('catalogsearch_fulltext')
            ->changeStatus(Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX);
        $indexer->getProcessByCode('catalog_category_product')
            ->changeStatus(Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX);

        return $this;
    }
}
