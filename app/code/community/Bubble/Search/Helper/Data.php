<?php
/**
 * Search helper.
 *
 * @category Bubble
 * @package Bubble_Search
 * @author Johann Reinke <johann@bubblecode.net>
 */
class Bubble_Search_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Allowed languages.
     * Example: array('en_US' => 'en', 'fr_FR' => 'fr')
     *
     * @var array
     */
    protected $_languageCodes = array();

    /**
     * Searchable attributes.
     *
     * @var array
     */
    protected $_searchableAttributes;

    /**
     * Sortable attributes.
     *
     * @var array
     */
    protected $_sortableAttributes;

    /**
     * Text field types.
     *
     * @var array
     */
    protected $_textFieldTypes = array(
        'text',
        'varchar',
    );

    /**
     * Unlocalized field types.
     *
     * @var array
     */
    protected $_unlocalizedFieldTypes = array(
        'datetime',
        'decimal',
    );

    /**
     * Returns attribute field name (localized if needed).
     *
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute
     * @param string $localeCode
     * @return string
     */
    public function getAttributeFieldName($attribute, $localeCode = null)
    {
        if (is_string($attribute)) {
            $this->getSearchableAttributes(); // populate searchable attributes if not already set
            if (!isset($this->_searchableAttributes[$attribute])) {
                return $attribute;
            }
            $attribute = $this->_searchableAttributes[$attribute];
        }
        $attributeCode = $attribute->getAttributeCode();
        $backendType = $attribute->getBackendType();

        if ($attributeCode != 'score' && in_array($backendType, $this->_textFieldTypes)) {
            if (null === $localeCode) {
                $localeCode = $this->getLocaleCode();
            }
            $languageCode = $this->getLanguageCodeByLocaleCode($localeCode);
            $languageSuffix = $languageCode ? '_' . $languageCode : '';
            $attributeCode .= $languageSuffix;
        }

        return $attributeCode;
    }

    /**
     * Returns cache lifetime in seconds.
     *
     * @return int
     */
    public function getCacheLifetime()
    {
        return Mage::getStoreConfig('core/cache/lifetime');
    }

    /**
     * Returns search engine config data.
     *
     * @param string $prefix
     * @param mixed $store
     * @return array
     */
    public function getEngineConfigData($prefix = '', $store = null)
    {
        $config = Mage::getStoreConfig('catalog/search', $store);
        $data = array();
        if ($prefix) {
            foreach ($config as $key => $value) {
                $matches = array();
                if (preg_match("#^{$prefix}(.*)#", $key, $matches)) {
                    $data[$matches[1]] = $value;
                }
            }
        } else {
            $data = $config;
        }

        return $data;
    }

    /**
     * Returns EAV config singleton.
     *
     * @return Mage_Eav_Model_Config
     */
    public function getEavConfig()
    {
        return Mage::getSingleton('eav/config');
    }

    /**
     * Returns language code of specified locale code.
     *
     * @param string $localeCode
     * @return bool
     */
    public function getLanguageCodeByLocaleCode($localeCode)
    {
        $localeCode = (string) $localeCode;
        if (!$localeCode) {
            return false;
        }

        if (!isset($this->_languageCodes[$localeCode])) {
            $languages = $this->getSupportedLanguages();
            $this->_languageCodes[$localeCode] = false;
            foreach ($languages as $code => $locales) {
                if (is_array($locales)) {
                    if (in_array($localeCode, $locales)) {
                        $this->_languageCodes[$localeCode] = $code;
                    }
                } elseif ($localeCode == $locales) {
                    $this->_languageCodes[$localeCode] = $code;
                }
            }
        }

        return $this->_languageCodes[$localeCode];
    }

    /**
     * Returns store language code.
     *
     * @param mixed $store
     * @return bool
     */
    public function getLanguageCodeByStore($store = null)
    {
        return $this->getLanguageCodeByLocaleCode($this->getLocaleCode($store));
    }

    /**
     * Returns store locale code.
     *
     * @param null $store
     * @return string
     */
    public function getLocaleCode($store = null)
    {
        return Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $store);
    }

    /**
     * Retrieves all searchable product attributes.
     * Possibility to filter attributes by backend type.
     *
     * @param array $backendType
     * @return array
     */
    public function getSearchableAttributes($backendType = null)
    {
        if (null === $this->_searchableAttributes) {
            $this->_searchableAttributes = array();
            $entityType = $this->getEavConfig()->getEntityType('catalog_product');
            $entity = $entityType->getEntity();

            /* @var $productAttributeCollection Mage_Catalog_Model_Resource_Product_Attribute_Collection */
            $productAttributeCollection = Mage::getResourceModel('catalog/product_attribute_collection')
                ->setEntityTypeFilter($entityType->getEntityTypeId())
                ->addVisibleFilter()
                ->addToIndexFilter(true);

            $attributes = $productAttributeCollection->getItems();
            foreach ($attributes as $attribute) {
                /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
                $attribute->setEntity($entity);
                $this->_searchableAttributes[$attribute->getAttributeCode()] = $attribute;
            }
        }

        if (null !== $backendType) {
            $backendType = (array) $backendType;
            $attributes = array();
            foreach ($this->_searchableAttributes as $attribute) {
                /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
                if (in_array($attribute->getBackendType(), $backendType)) {
                    $attributes[$attribute->getAttributeCode()] = $attribute;
                }
            }

            return $attributes;
        }

        return $this->_searchableAttributes;
    }

    /**
     * Returns seach config data.
     *
     * @param string $field
     * @param mixed $store
     * @return array
     */
    public function getSearchConfigData($field, $store = null)
    {
        $path = 'catalog/search/' . $field;

        return Mage::getStoreConfig($path, $store);
    }

    /**
     * Returns searched parameter as array.
     *
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute
     * @param mixed $value
     * @return array
     */
    public function getSearchParam($attribute, $value)
    {
        if (empty($value) ||
            (isset($value['from']) && empty($value['from']) &&
                isset($value['to']) && empty($value['to']))) {
            return false;
        }

        $field = $this->getAttributeFieldName($attribute);
        $backendType = $attribute->getBackendType();
        if ($backendType == 'datetime') {
            $format = Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT);
            if (is_array($value)) {
                foreach ($value as &$val) {
                    if (!is_empty_date($val)) {
                        $date = new Zend_Date($val, $format);
                        $val = $date->toString(Zend_Date::ISO_8601) . 'Z';
                    }
                }
                unset($val);
            } else {
                if (!is_empty_date($value)) {
                    $date = new Zend_Date($value, $format);
                    $value = $date->toString(Zend_Date::ISO_8601) . 'Z';
                }
            }
        }

        if ($attribute->usesSource()) {
            $attribute->setStoreId(Mage::app()->getStore()->getId());
        }

        return array($field => $value);
    }

    /**
     * Returns sortable attribute field name (localized if needed).
     *
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute
     * @param string $locale
     * @return string
     */
    public function getSortableAttributeFieldName($attribute, $locale = null)
    {
        if (is_string($attribute)) {
            $this->getSortableAttributes(); // populate sortable attributes if not already set
            if (!isset($this->_sortableAttributes[$attribute])) {
                return $attribute;
            }
            $attribute = $this->_sortableAttributes[$attribute];
        }

        $attributeCode = $attribute->getAttributeCode();

        if ($attributeCode != 'score' && !in_array($attribute->getBackendType(), $this->_unlocalizedFieldTypes)) {
            if (null === $locale) {
                $locale = $this->getLocaleCode();
            }
            $languageCode = $this->getLanguageCodeByLocaleCode($locale);
            $languageSuffix = $languageCode ? '_' . $languageCode : '';
            $attributeCode .= $languageSuffix;
        }

        return 'sort_by_' . $attributeCode;
    }

    /**
     * Retrieves all sortable product attributes.
     *
     * @return array
     */
    public function getSortableAttributes()
    {
        if (null === $this->_sortableAttributes) {
            $this->_sortableAttributes = Mage::getSingleton('catalog/config')->getAttributesUsedForSortBy();
            if (array_key_exists('price', $this->_sortableAttributes)) {
                unset($this->_sortableAttributes['price']); // Price sorting is handled with searchable attribute.
            }
        }

        return $this->_sortableAttributes;
    }

    /**
     * Defines supported languages for snowball filter.
     *
     * @return array
     */
    public function getSupportedLanguages()
    {
        $default = array(
            /**
             * SnowBall filter based
             */
            // Danish
            'da' => 'da_DK',
            // Dutch
            'nl' => 'nl_NL',
            // English
            'en' => array('en_AU', 'en_CA', 'en_NZ', 'en_GB', 'en_US'),
            // Finnish
            'fi' => 'fi_FI',
            // French
            'fr' => array('fr_CA', 'fr_FR'),
            // German
            'de' => array('de_DE','de_DE','de_AT'),
            // Hungarian
            'hu' => 'hu_HU',
            // Italian
            'it' => array('it_IT','it_CH'),
            // Norwegian
            'nb' => array('nb_NO', 'nn_NO'),
            // Portuguese
            'pt' => array('pt_BR', 'pt_PT'),
            // Romanian
            'ro' => 'ro_RO',
            // Russian
            'ru' => 'ru_RU',
            // Spanish
            'es' => array('es_AR', 'es_CL', 'es_CO', 'es_CR', 'es_ES', 'es_MX', 'es_PA', 'es_PE', 'es_VE'),
            // Swedish
            'sv' => 'sv_SE',
            // Turkish
            'tr' => 'tr_TR',

            /**
             * Lucene class based
             */
            // Czech
            'cs' => 'cs_CZ',
            // Greek
            'el' => 'el_GR',
            // Thai
            'th' => 'th_TH',
            // Chinese
            'zh' => array('zh_CN', 'zh_HK', 'zh_TW'),
            // Japanese
            'ja' => 'ja_JP',
            // Korean
            'ko' => 'ko_KR'
        );

        return $default;
    }
    
    /**
     * Get Status of catalogsearch_fulltext index
     *
     * @return string
     */
    public function getIndexStatus()
    {
        if (!($indexStatus = Mage::registry('bubble_search.index_status'))) {
            $indexStatus = Mage::getSingleton('index/indexer')
                ->getProcessByCode('catalogsearch_fulltext')
                ->getStatus();
            Mage::register('bubble_search.index_status', $indexStatus);
        }
        return $indexStatus;
    }

    /**
     * Checks if configured engine is active.
     *
     * @return bool
     */
    public function isActiveEngine()
    {
        $engine = $this->getSearchConfigData('engine');
        if ($engine && Mage::getConfig()->getResourceModelClassName($engine)) {
            $model = Mage::getResourceSingleton($engine);
            return $model
                && $model instanceof Bubble_Search_Model_Resource_Engine_Abstract
                && ($this->getIndexStatus() != 'working')
                && $model->test();
        }

        return false;
    }

    /**
     * Checks if specified attribute is indexable by search engine.
     *
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute
     * @return bool
     */
    public function isAttributeIndexable($attribute)
    {
        if ($attribute->getBackendType() == 'varchar' && !$attribute->getBackendModel()) {
            return true;
        }

        if ($attribute->getBackendType() == 'int'
            && $attribute->getSourceModel() != 'eav/entity_attribute_source_boolean'
            && ($attribute->getIsSearchable() || $attribute->getIsFilterable() || $attribute->getIsFilterableInSearch())
        ) {
            return true;
        }

        if ($attribute->getIsSearchable() || $attribute->getIsFilterable() || $attribute->getIsFilterableInSearch()) {
            return true;
        }

        return false;
    }

    /**
     * Checks if specified attribute is using options.
     *
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute
     * @return bool
     */
    public function isAttributeUsingOptions($attribute)
    {
        $model = Mage::getModel($attribute->getSourceModel());

        return $attribute->usesSource() &&
               $attribute->getBackendType() == 'int' &&
               $model instanceof Mage_Eav_Model_Entity_Attribute_Source_Table;
    }

    /**
     * Checks if debug mode is enabled.
     *
     * @return bool
     */
    public function isDebugEnabled()
    {
        $config = $this->getEngineConfigData();

        return array_key_exists('enable_debug_mode', $config) && $config['enable_debug_mode'];
    }

    /**
     * Method that can be overriden for customing product data indexation.
     *
     * @param array $index
     * @param string $separator
     * @return array
     */
    public function prepareIndexData($index, $separator = null)
    {
        return $index;
    }

    /**
     * Forces error display.
     *
     * @param string $error
     */
    public function showError($error)
    {
        echo Mage::app()->getLayout()->createBlock('core/messages')
            ->addError($error)->getGroupedHtml();
    }
}
