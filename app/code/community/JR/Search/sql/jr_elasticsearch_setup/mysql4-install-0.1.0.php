<?php
/**
 * Adds search_weight column to catalog_eav_attribute table.
 *
 * @var $installer Mage_Core_Model_Resource_Setup
 */
$installer = $this;
$installer->startSetup();

try {
    $installer->getConnection()->addColumn(
        $installer->getTable('catalog/eav_attribute'),
        'search_weight',
        "tinyint(1) unsigned NOT NULL DEFAULT '1' after `is_searchable`"
    );
} catch (Exception $e) {
    // ignore
}

$installer->endSetup();