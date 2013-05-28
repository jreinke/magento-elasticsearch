<?php
/**
 * Elasticsearch client.
 *
 * @category Bubble
 * @package Bubble_Search
 * @author Johann Reinke <johann@bubblecode.net>
 */
abstract class Bubble_Search_Model_Resource_Engine_Abstract
{
    const DEFAULT_ROWS_LIMIT = 9999;

    const UNIQUE_KEY = 'unique';

    /**
     * @var string List of advanced index fields prefix.
     */
    protected $_advancedIndexFieldsPrefix = '#';

    /**
     * @var array List of advanced dynamic index fields.
     */
    protected $_advancedDynamicIndexFields = array(
        '#position_category_',
        '#price_'
    );

    /**
     * @var object Search engine client.
     */
    protected $_client;

    /**
     * @var array List of dates format.
     */
    protected $_dateFormats = array();

    /**
     * @var array List of default query parameters.
     */
    protected $_defaultQueryParams = array(
        'offset' => 0,
        'limit' => 100,
        'sort_by' => array(array('relevance' => 'desc')),
        'store_id' => null,
        'locale_code' => null,
        'fields' => array(),
        'params' => array(),
        'ignore_handler' => false,
        'filters' => array(),
    );

    /**
     * @var array List of indexable attribute parameters.
     */
    protected $_indexableAttributeParams = array();

    /**
     * @var int Last number of results found.
     */
    protected $_lastNumFound;

    /**
     * @var array List of non fulltext fields.
     */
    protected $_notInFulltextField = array(
        self::UNIQUE_KEY,
        'id',
        'store_id',
        'in_stock',
        'categories',
        'show_in_categories',
        'visibility'
    );

    /**
     * @var bool Stores search engine availibility
     */
    protected $_test = null;

    /**
     * @var array List of used fields.
     */
    protected $_usedFields = array(
        self::UNIQUE_KEY,
        'id',
        'sku',
        'price',
        'store_id',
        'categories',
        'show_in_categories',
        'visibility',
        'in_stock',
        'score'
    );

    /**
     * Adds advanced index fields to index data.
     *
     * @param array $index
     * @param int $storeId
     * @param array $productIds
     * @return array
     */
    public function addAdvancedIndex($index, $storeId, $productIds = null)
    {
        return Mage::getResourceSingleton('bubble_search/engine_index')
            ->addAdvancedIndex($index, $storeId, $productIds);
    }

    /**
     * Returns advanced search results.
     *
     * @return Bubble_Search_Model_Resource_Catalog_Product_Collection
     */
    public function getAdvancedResultCollection()
    {
        return $this->getResultCollection();
    }

    /**
     * Checks if advanced index is allowed for current search engine.
     *
     * @return bool
     */
    public function allowAdvancedIndex()
    {
        return true;
    }

    /**
     * Cleans cache.
     *
     * @return Bubble_Search_Model_Resource_Engine_Abstract
     */
    public function cleanCache()
    {
        return $this;
    }

    /**
     * Returns product visibility ids for search.
     *
     * @see Mage_Catalog_Model_Product_Visibility
     * @return mixed
     */
    public function getAllowedVisibility()
    {
        return Mage::getSingleton('catalog/product_visibility')->getVisibleInSearchIds();
    }

    /**
     * Returns advanced index fields prefix.
     *
     * @return string
     */
    public function getFieldsPrefix()
    {
        return $this->_advancedIndexFieldsPrefix;
    }

    /**
     * Retrieves product ids for specified query.
     *
     * @param string $query
     * @param array $params
     * @param string $type
     * @return array
     */
    public function getIdsByQuery($query, $params = array(), $type = 'product')
    {
        $ids = array();
        $params['fields'] = array('id');
        $resultTmp = $this->search($query, $params, $type);
        if (!empty($resultTmp['ids'])) {
            foreach ($resultTmp['ids'] as $id) {
                $ids[] = $id['id'];
            }
        }
        $result = array(
            'ids' => $ids,
            'total_count' => (isset($resultTmp['total_count'])) ? $resultTmp['total_count'] : null,
            'faceted_data' => (isset($resultTmp['facets'])) ? $resultTmp['facets'] : array(),
        );

        return $result;
    }

    /**
     * Returns resource name.
     *
     * @return string
     */
    public function getResourceName()
    {
        return 'bubble_search/advanced';
    }

    /**
     * Returns last number of results found.
     *
     * @return int
     */
    public function getLastNumFound()
    {
        return $this->_lastNumFound;
    }

    /**
     * Returns catalog product collection with current search engine set.
     *
     * @return Bubble_Search_Model_Resource_Catalog_Product_Collection
     */
    public function getResultCollection()
    {
        return Mage::getResourceModel('bubble_search/catalog_product_collection')->setEngine($this);
    }

    /**
     * Retrieves stats for specified query.
     *
     * @param string $query
     * @param array $params
     * @param string $type
     * @return array
     */
    public function getStats($query, $params = array(), $type = 'product')
    {
        return $this->_search($query, $params, $type);
    }

    /**
     * Alias of isLayeredNavigationAllowed.
     *
     * @return bool
     */
    public function isLeyeredNavigationAllowed()
    {
        return $this->isLayeredNavigationAllowed();
    }

    /**
     * Checks if layered navigation is available for current search engine.
     *
     * @return bool
     */
    public function isLayeredNavigationAllowed()
    {
        return true;
    }

    /**
     * Prepares index data.
     * Should be overriden in child classes if needed.
     *
     * @param $index
     * @param string $separator
     * @return array
     */
    public function prepareEntityIndex($index, $separator = null)
    {
        return $this->_getHelper()->prepareIndexData($index, $separator);
    }

    /**
     * Performs search query and facetting.
     *
     * @param string $query
     * @param array $params
     * @param string $type
     * @return array
     */
    public function search($query, $params = array(), $type = 'product')
    {
        try {
            Varien_Profiler::start('BUBBLE_SEARCH');
            $result = $this->_search($query, $params, $type);
            Varien_Profiler::stop('BUBBLE_SEARCH');

            return $result;
        } catch (Exception $e) {
            Mage::logException($e);
            if ($this->_getHelper()->isDebugEnabled()) {
                $this->_getHelper()->showError($e->getMessage());
            }
        }

        return array();
    }

    /**
     * Checks search engine availability.
     * Should be overriden by child classes.
     *
     * @return bool
     */
    public function test()
    {
        return true;
    }

    /**
     * Transforms specified date to basic YYYY-MM-dd format.
     *
     * @param int $storeId
     * @param string $date
     * @return null|string
     */
    protected function _getDate($storeId, $date = null)
    {
        if (!isset($this->_dateFormats[$storeId])) {
            $timezone = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, $storeId);
            $locale   = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $storeId);
            $locale   = new Zend_Locale($locale);

            $dateObj  = new Zend_Date(null, null, $locale);
            $dateObj->setTimezone($timezone);
            $this->_dateFormats[$storeId] = array($dateObj, $locale->getTranslation(null, 'date', $locale));
        }

        if (is_empty_date($date)) {
            return null;
        }

        list($dateObj, $localeDateFormat) = $this->_dateFormats[$storeId];
        $dateObj->setDate($date, $localeDateFormat);

        return $dateObj->toString('YYYY-MM-dd');
    }

    /**
     * Returns search helper.
     *
     * @return Bubble_Search_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('bubble_search');
    }

    /**
     * Returns indexable attribute parameters.
     *
     * @return array
     */
    protected function _getIndexableAttributeParams()
    {
        if (null === $this->_indexableAttributeParams) {
            $this->_indexableAttributeParams = array();
            $attributes = $this->_getHelper()->getSearchableAttributes();
            foreach ($attributes as $attribute) {
                /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
                $this->_indexableAttributeParams[$attribute->getAttributeCode()] = array(
                    'backend_type'   => $attribute->getBackendType(),
                    'frontend_input' => $attribute->getFrontendInput(),
                    'search_weight'  => $attribute->getSearchWeight(),
                    'is_searchable'  => $attribute->getIsSearchable()
                );
            }
        }

        return $this->_indexableAttributeParams;
    }

    /**
     * Returns store locale code.
     *
     * @param int $storeId
     * @return string
     */
    protected function _getLocaleCode($storeId = null)
    {
        return $this->_getHelper()->getLocaleCode($storeId);
    }

    /**
     * Transforms specified object to an array.
     *
     * @param $object
     * @return array
     */
    protected function _objectToArray($object)
    {
        if (!is_object($object) && !is_array($object)){
            return $object;
        }
        if (is_object($object)){
            $object = get_object_vars($object);
        }

        return array_map(array($this, '_objectToArray'), $object);
    }

    /**
     * @param array $docsData
     * @param string $type
     * @param string $localeCode
     * @return array
     */
    protected function _prepareDocs($docsData, $type, $localeCode = null)
    {
        if (!is_array($docsData) || empty($docsData)) {
            return array();
        }

        $docs = array();
        foreach ($docsData as $entityId => $index) {
            $index[self::UNIQUE_KEY] = $entityId . '|' . $index['store_id'];
            $index['id'] = $entityId;
            $index = $this->_prepareIndexData($index, $localeCode);
            $docs[] = $this->_createDoc($entityId, $index, $type);
        }

        return $docs;
    }

    /**
     * Prepares index data before indexation.
     *
     * @param array $data
     * @param string $localeCode
     * @return array
     */
    protected function _prepareIndexData($data, $localeCode = null)
    {
        if (!is_array($data) || empty($data)) {
            return array();
        }

        foreach ($data as $key => $value) {
            if (in_array($key, $this->_usedFields)) {
                continue;
            } elseif ($key == 'options') {
                unset($data[$key]);
                continue;
            }
            $field = $this->_getHelper()->getAttributeFieldName($key, $localeCode);
            $field = str_replace($this->_advancedIndexFieldsPrefix, '', $field);
            if ($field != $key) {
                $data[$field] = $value;
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Prepares query before search.
     *
     * @param mixed $query
     * @return string
     */
    protected function _prepareSearchConditions($query)
    {
        return $query;
    }

    /**
     * Cleans index.
     *
     * @abstract
     * @param int $storeId
     * @param int $id
     * @param string $type
     * @return mixed
     */
    abstract public function cleanIndex($storeId = null, $id = null, $type = 'product');

    /**
     * Deletes index.
     *
     * @abstract
     * @return mixed
     */
    abstract public function deleteIndex();

    /**
     * Saves products data in index.
     *
     * @abstract
     * @param int $storeId
     * @param array $indexes
     * @param string $type
     * @return mixed
     */
    abstract public function saveEntityIndexes($storeId, $indexes, $type = 'product');

    /**
     * Adds documents to index.
     *
     * @abstract
     * @param array $docs
     * @return mixed
     */
    abstract protected function _addDocs($docs);

    /**
     * Creates and prepares document for indexation.
     *
     * @abstract
     * @param int $entityId
     * @param array $index
     * @param string $type
     * @return mixed
     */
    abstract protected function _createDoc($entityId, $index, $type = 'product');

    /**
     * Prepares facets query response.
     *
     * @abstract
     * @param mixed $response
     * @return mixed
     */
    abstract protected function _prepareFacetsQueryResponse($response);

    /**
     * Prepares query response.
     *
     * @abstract
     * @param mixed $response
     * @return mixed
     */
    abstract protected function _prepareQueryResponse($response);

    /**
     * Performs search and facetting for specified query and parameters.
     *
     * @abstract
     * @param string $query
     * @param array $params
     * @param string $type
     * @return mixed
     */
    abstract protected function _search($query, $params = array(), $type = 'product');
}
