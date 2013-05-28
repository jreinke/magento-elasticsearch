<?php
/**
 * Search observer.
 *
 * @category Bubble
 * @package Bubble_Search
 * @author Johann Reinke <johann@bubblecode.net>
 */
class Bubble_Search_Model_Observer
{
    /**
     * Adds search weight parameter in attribute form.
     *
     * @param Varien_Event_Observer $observer
     */
    public function eavAttributeEditFormInit(Varien_Event_Observer $observer)
    {
        /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
        $attribute = $observer->getEvent()->getAttribute();
        $form = $observer->getEvent()->getForm();
        $fieldset = $form->getElement('front_fieldset');

        $fieldset->addField('search_weight', 'select', array(
            'name' => 'search_weight',
            'label' => Mage::helper('catalog')->__('Search Weight'),
            'values' => array(
                1 => 1,
                2 => 2,
                3 => 3,
                4 => 4,
                5 => 5
            ),
        ), 'is_searchable');

        if ($attribute->getAttributeCode() == 'name') {
            $form->getElement('is_searchable')->setDisabled(1);
        }
    }

    /**
     * Requires catalog search indexation.
     *
     * @param Varien_Event_Observer $observer
     */
    public function requireCatalogsearchReindex(Varien_Event_Observer $observer)
    {
        if (Mage::helper('bubble_search')->isActiveEngine()) {
            /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
            $attribute = $observer->getEvent()->getAttribute();
            if ($attribute->getData('search_weight') != $attribute->getOrigData('search_weight')) {
                Mage::getSingleton('index/indexer')->getProcessByCode('catalogsearch_fulltext')
                    ->changeStatus(Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX);
            }
        }
    }

    /**
     * Deletes index if full catalog search reindexation is asked.
     *
     * @param Varien_Event_Observer $observer
     */
    public function beforeIndexProcessStart(Varien_Event_Observer $observer)
    {
        $storeId = $observer->getEvent()->getStoreId();
        $productIds = $observer->getEvent()->getProductIds();
        if (null === $storeId && null === $productIds) {
            $engine = Mage::helper('catalogsearch')->getEngine();
            if ($engine instanceof Bubble_Search_Model_Resource_Engine_Abstract) {
                $engine->cleanCache();
                $engine->deleteIndex();
            }
        }
    }
}