<?php
/**
 * Elasticsearch client.
 *
 * @category Bubble
 * @package Bubble_Search
 * @author Johann Reinke <johann@bubblecode.net>
 */
class Bubble_Search_Model_Resource_Engine_Elasticsearch_Client extends Elastica_Client
{
    /**
     * @var string Index name.
     */
    protected $_index;

    /**
     * @var string Date format.
     * @link http://www.elasticsearch.org/guide/reference/mapping/date-format.html
     */
    protected $_dateFormat = 'date';

    /**
     * @var array Stop languages for token filter.
     * @link http://www.elasticsearch.org/guide/reference/index-modules/analysis/stop-tokenfilter.html
     */
    protected $_stopLanguages = array(
        'arabic', 'armenian', 'basque', 'brazilian', 'bulgarian', 'catalan', 'czech',
        'danish', 'dutch', 'english', 'finnish', 'french', 'galician', 'german', 'greek',
        'hindi', 'hungarian', 'indonesian', 'italian', 'norwegian', 'persian', 'portuguese',
        'romanian', 'russian', 'spanish', 'swedish', 'turkish',
    );

    /**
     * @var array Snowball languages.
     * @link http://www.elasticsearch.org/guide/reference/index-modules/analysis/snowball-tokenfilter.html
     */
    protected $_snowballLanguages = array(
        'Armenian', 'Basque', 'Catalan', 'Danish', 'Dutch', 'English', 'Finnish', 'French',
        'German', 'Hungarian', 'Italian', 'Kp', 'Lovins', 'Norwegian', 'Porter', 'Portuguese',
        'Romanian', 'Russian', 'Spanish', 'Swedish', 'Turkish',
    );

    /**
     * Initializes search engine config and index name.
     */
    public function __construct()
    {
        $config = $this->_getHelper()->getEngineConfigData();
        parent::__construct($config);
        if (!isset($config['index'])) {
            Mage::throwException('Index must be defined for search engine client.');
        }
        $this->setIndex($config['index']);
    }

    /**
     * Cleans index.
     *
     * @param int $storeId
     * @param int $id
     * @param string $type
     * @return mixed
     */
    public function cleanIndex($storeId = null, $id = null, $type = 'product')
    {
        $this->_prepareIndex();
        if ($this->getStatus()->indexExists($this->_index)) {
            if (null === $storeId) {
                // no store filter
                if (empty($id)) {
                    // delete ALL docs of type $type
                    return $this->getIndex($this->_index)->getType($type)->delete();
                } else {
                    // delete docs of type $type with _id in $id
                    foreach (Mage::app()->getStores() as $store) {
                        $this->cleanIndex($store->getId(), $id, $type);
                    }
                }
            } else {
                if (empty($id)) {
                    // delete ALL docs from specific store
                    $path = sprintf('%s/%s/_query', $this->_index, $type);
                    $query = new Elastica_Query_Term();
                    $query->setTerm('store_id', $storeId);
                    $response = $this->request($path, Elastica_Request::DELETE, $query->toArray());

                    return new Elastica_ResultSet($response);
                } else {
                    // delete docs from specific store with _id in $id
                    $ids = (array) $id;
                    foreach ($ids as &$id) {
                        $id .= '|' . $storeId;
                    }
                    unset($id);

                    return $this->deleteIds($ids, $this->_index, $type);
                }
            }
        }

        return $this;
    }

    /**
     * Create document to index.
     *
     * @param string $id
     * @param array $data
     * @param string $type
     * @return Elastica_Document
     */
    public function createDoc($id = '', array $data = array(), $type = 'product')
    {
        return new Elastica_Document($id, $data, $type, $this->_index);
    }

    /**
     * Deletes index.
     *
     * @return bool|Elastica_Response
     */
    public function deleteIndex()
    {
        if ($this->getStatus()->indexExists($this->_index)) {
            return $this->getIndex($this->_index)->delete();
        }

        return true;
    }

    /**
     * Returns facets max size parameter.
     *
     * @return int
     */
    public function getFacetsMaxSize()
    {
        return (int) $this->getConfig('facets_max_size');
    }

    /**
     * Returns fuzzy max query terms parameter.
     *
     * @link http://www.elasticsearch.org/guide/reference/query-dsl/flt-query.html
     * @return int
     */
    public function getFuzzyMaxQueryTerms()
    {
        return (int) $this->getConfig('fuzzy_max_query_terms');
    }

    /**
     * Returns fuzzy min similarity parameter.
     *
     * @link http://www.elasticsearch.org/guide/reference/query-dsl/flt-query.html
     * @return float
     */
    public function getFuzzyMinSimilarity()
    {
        // 0 to 1 (1 excluded)
        return min(0.99, max(0, $this->getConfig('fuzzy_min_similarity')));
    }

    /**
     * Returns fuzzy prefix length.
     *
     * @link http://www.elasticsearch.org/guide/reference/query-dsl/flt-query.html
     * @return int
     */
    public function getFuzzyPrefixLength()
    {
        return (int) $this->getConfig('fuzzy_prefix_length');
    }

    /**
     * Returns fuzzy query boost parameter.
     *
     * @link http://www.elasticsearch.org/guide/reference/query-dsl/flt-query.html
     * @return float
     */
    public function getFuzzyQueryBoost()
    {
        return (float) $this->getConfig('fuzzy_query_boost');
    }

    /**
     * Checks if fuzzy query is enabled.
     *
     * @link http://www.elasticsearch.org/guide/reference/query-dsl/flt-query.html
     * @return bool
     */
    public function isFuzzyQueryEnabled()
    {
        return (bool) $this->getConfig('enable_fuzzy_query');
    }

    /**
     * Checks if ICU folding is enabled.
     *
     * @link http://www.elasticsearch.org/guide/reference/index-modules/analysis/icu-plugin.html
     * @return bool
     */
    public function isIcuFoldingEnabled()
    {
        return (bool) $this->getConfig('enable_icu_folding');
    }

    /**
     * Refreshes index
     *
     * @return Bubble_Search_Model_Resource_Engine_Elasticsearch_Client
     */
    public function refreshIndex()
    {
        if ($this->getStatus()->indexExists($this->_index)) {
            $this->getIndex($this->_index)->refresh();
        }

        return $this;
    }

    /**
     * Handles search and facets.
     *
     * @param string $q
     * @param array $params
     * @param string $type
     * @return Elastica_ResultSet
     * @throws Exception
     */
    public function search($q, $params = array(), $type = 'product')
    {
        if ($this->getStatus()->indexExists($this->_index)) {
            Varien_Profiler::start('ELASTICA_SEARCH');
            if (empty($params['filters'])) {
                $params['filters'] = '*';
            }
            $queryFilter = new Elastica_Filter_Query(new Elastica_Query_QueryString($params['filters']));
            if (isset($params['range_filters']) && !empty($params['range_filters'])) {
                $andFilter = new Elastica_Filter_And();
                $andFilter->addFilter($queryFilter);
                $filter = new Elastica_Filter_Range();
                foreach ($params['range_filters'] as $field => $rangeFilter) {
                    $filter->addField($field, $rangeFilter);
                }
                $andFilter->addFilter($filter);
                $queryFilter = $andFilter;
            }

            if (empty($q)) {
                $baseQuery = new Elastica_Query_MatchAll();
            } else {
                $baseQuery = new Elastica_Query_Bool();

                if ($this->isFuzzyQueryEnabled()) {
                    $fields = $this->_getSearchFields(true, $q);
                    $queryFuzzy = new Elastica_Query_FuzzyLikeThis();
                    $queryFuzzy->addFields($fields);
                    $queryFuzzy->setLikeText($q);
                    $queryFuzzy->setMinSimilarity($this->getFuzzyMinSimilarity());
                    $queryFuzzy->setPrefixLength($this->getFuzzyPrefixLength());
                    $queryFuzzy->setMaxQueryTerms($this->getFuzzyMaxQueryTerms());
                    $queryFuzzy->setBoost($this->getFuzzyQueryBoost());
                    $baseQuery->addShould($queryFuzzy);
                }

                $queryString = new Elastica_Query_QueryString($q);
                $queryString->setFields($this->_getSearchFields(false, $q));
                $baseQuery->addShould($queryString);
            }

            $filteredQuery = new Elastica_Query_Filtered($baseQuery, $queryFilter);
            $query = Elastica_Query::create($filteredQuery)
                ->setFrom($params['offset'])
                ->setLimit($params['limit']);

            if (isset($params['facets']['queries']) && !empty($params['facets']['queries'])) {
                foreach ($params['facets']['queries'] as $facetQuery) {
                    $facet = new Elastica_Facet_Query($facetQuery);
                    $facet->setParam('query_string', array('query' => $facetQuery));
                    $query->addFacet($facet);
                }
            }

            if (isset($params['stats']['fields']) && !empty($params['stats']['fields'])) {
                foreach ($params['stats']['fields'] as $field) {
                    $facet = new Elastica_Facet_Statistical($field);
                    $facet->setParam('field', $field);
                    $query->addFacet($facet);
                }
            } else {
                if (isset($params['facets']['fields']) && !empty($params['facets']['fields'])) {
                    $properties = $this->_getIndexProperties();
                    foreach ($params['facets']['fields'] as $field) {
                        if (array_key_exists($field, $properties)) {
                            $facet = new Elastica_Facet_Terms($field);
                            if ($properties[$field]['type'] == 'multi_field') {
                                $field .= '.untouched';
                            }
                            $facet->setField($field);
                            $facet->setParam('all_terms', true);
                            $facet->setSize($this->getFacetsMaxSize());
                            $query->addFacet($facet);
                        }
                    }
                }

                if (isset($params['facets']['ranges']) && !empty($params['facets']['ranges'])) {
                    foreach ($params['facets']['ranges'] as $field => $ranges) {
                        $facet = new Elastica_Facet_Range($field);
                        $facet->setField($field);
                        $facet->setRanges($ranges);
                        $query->addFacet($facet);
                    }
                }
            }

            if (isset($params['sort']) && !empty($params['sort'])) {
                foreach ($params['sort'] as $sort) {
                    $query->addSort($sort);
                }
            }

            $result = $this->getIndex($this->_index)
                ->getType($type)
                ->search($query);

            Varien_Profiler::stop('ELASTICA_SEARCH');

            return $result;
        }

        return array();
    }

    /**
     * Stores index name.
     *
     * @param $index
     * @return Bubble_Search_Model_Resource_Engine_Elasticsearch_Client
     */
    public function setIndex($index)
    {
        $this->_index = $index;

        return $this;
    }

    /**
     * Returns attribute type for indexation.
     *
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute
     * @return string
     */
    protected function _getAttributeType($attribute)
    {
        $type = 'string';
        if ($attribute->getBackendType() == 'decimal') {
            $type = 'double';
        } elseif ($attribute->getSourceModel() == 'eav/entity_attribute_source_boolean') {
            $type = 'boolean';
        } elseif ($attribute->getBackendType() == 'datetime') {
            $type = 'date';
        } elseif ($attribute->usesSource() || $attribute->getFrontendClass() == 'validate-digits') {
            $type = 'integer';
        }

        return $type;
    }

    /**
     * Returns search helper.
     *
     * @return Bubble_Search_Helper_Elasticsearch
     */
    protected function _getHelper()
    {
        return Mage::helper('bubble_search/elasticsearch');
    }

    /**
     * Builds index properties for indexation according to available attributes and stores.
     *
     * @return array
     */
    protected function _getIndexProperties()
    {
        $cacheId = Bubble_Search_Model_Resource_Engine_Elasticsearch::CACHE_INDEX_PROPERTIES_ID;
        if ($properties = Mage::app()->loadCache($cacheId)) {
            return unserialize($properties);
        }

        /** @var $helper Bubble_Search_Helper_Data */
        $helper = $this->_getHelper();
        $indexSettings = $this->_getIndexSettings();
        $properties = array();

        $attributes = $helper->getSearchableAttributes(array('varchar', 'int'));
        foreach ($attributes as $attribute) {
            /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
            if ($this->_isAttributeIndexable($attribute)) {
                foreach (Mage::app()->getStores() as $store) {
                    /** @var $store Mage_Core_Model_Store */
                    $locale = $helper->getLocaleCode($store);
                    $key = $helper->getAttributeFieldName($attribute, $locale);
                    $type = $this->_getAttributeType($attribute);
                    if ($type !== 'string') {
                        $properties[$key] = array(
                            'type' => $type,
                        );
                    } else {
                        $weight = $attribute->getSearchWeight();
                        $properties[$key] = array(
                            'type' => 'multi_field',
                            'fields' => array(
                                $key => array(
                                    'type' => $type,
                                    'boost' => $weight > 0 ? $weight : 1,
                                ),
                                'untouched' => array(
                                    'type' => $type,
                                    'index' => 'not_analyzed',
                                ),
                            ),
                        );
                        foreach (array_keys($indexSettings['analysis']['analyzer']) as $analyzer) {
                            $properties[$key]['fields'][$analyzer] = array(
                                'type' => 'string',
                                'analyzer' => $analyzer,
                                'boost' => $attribute->getSearchWeight(),
                            );
                        }
                    }
                }
            }
        }

        $attributes = $helper->getSearchableAttributes('text');
        foreach ($attributes as $attribute) {
            /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
            foreach (Mage::app()->getStores() as $store) {
                /** @var $store Mage_Core_Model_Store */
                $languageCode = $helper->getLanguageCodeByStore($store);
                $locale = $helper->getLocaleCode($store);
                $key = $helper->getAttributeFieldName($attribute, $locale);
                $weight = $attribute->getSearchWeight();
                $properties[$key] = array(
                    'type' => 'string',
                    'boost' => $weight > 0 ? $weight : 1,
                    'analyzer' => 'analyzer_' . $languageCode,
                );
            }
        }

        $attributes = $helper->getSearchableAttributes(array('static', 'varchar', 'decimal', 'datetime'));
        foreach ($attributes as $attribute) {
            /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
            $key = $helper->getAttributeFieldName($attribute);
            if ($this->_isAttributeIndexable($attribute) && !isset($properties[$key])) {
                $weight = $attribute->getSearchWeight();
                $properties[$key] = array(
                    'type' => $this->_getAttributeType($attribute),
                    'boost' => $weight > 0 ? $weight : 1,
                );
                if ($attribute->getBackendType() == 'datetime') {
                    $properties[$key]['format'] = $this->_dateFormat;
                }
            }
        }

        // Handle sortable attributes
        $attributes = $helper->getSortableAttributes();
        foreach ($attributes as $attribute) {
            /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
            $type = 'string';
            if ($attribute->getBackendType() == 'decimal') {
                $type = 'double';
            } elseif ($attribute->getBackendType() == 'datetime') {
                $type = 'date';
                $format = $this->_dateFormat;
            }
            foreach (Mage::app()->getStores() as $store) {
                /** @var $store Mage_Core_Model_Store */
                $locale = $helper->getLocaleCode($store);
                $key = $helper->getSortableAttributeFieldName($attribute, $locale);
                if (!array_key_exists($key, $properties)) {
                    $properties[$key] = array(
                        'type' => $type,
                        'index' => 'not_analyzed',
                    );
                    if (isset($format)) {
                        $properties[$key]['format'] = $format;
                    }
                }
            }
        }

        // Custom attributes indexation
        $properties['visibility'] = array(
            'type' => 'integer',
        );
        $properties['store_id'] = array(
            'type' => 'integer',
        );
        $properties['in_stock'] = array(
            'type' => 'boolean',
        );

        if (Mage::app()->useCache('config')) {
            $lifetime = $this->_getHelper()->getCacheLifetime();
            Mage::app()->saveCache(serialize($properties), $cacheId, array('config'), $lifetime);
        }

        return $properties;
    }

    /**
     * Returns indexation analyzers and filters configuration.
     *
     * @return array
     */
    protected function _getIndexSettings()
    {
        $indexSettings = array();
        $indexSettings['number_of_replicas'] = (int) $this->getConfig('number_of_replicas');
        $indexSettings['analysis']['analyzer'] = array(
            'whitespace' => array(
                'tokenizer' => 'standard',
                'filter' => array('lowercase'),
            ),
            'edge_ngram_front' => array(
                'tokenizer' => 'standard',
                'filter' => array('length', 'edge_ngram_front', 'lowercase'),
            ),
            'edge_ngram_back' => array(
                'tokenizer' => 'standard',
                'filter' => array('length', 'edge_ngram_back', 'lowercase'),
            ),
            'shingle' => array(
                'tokenizer' => 'standard',
                'filter' => array('shingle', 'length', 'lowercase'),
            ),
            'shingle_strip_ws' => array(
                'tokenizer' => 'standard',
                'filter' => array('shingle', 'strip_whitespaces', 'length', 'lowercase'),
            ),
            'shingle_strip_apos_and_ws' => array(
                'tokenizer' => 'standard',
                'filter' => array('shingle', 'strip_apostrophes', 'strip_whitespaces', 'length', 'lowercase'),
            ),
        );
        $indexSettings['analysis']['filter'] = array(
            'shingle' => array(
                'type' => 'shingle',
                'max_shingle_size' => 20,
                'output_unigrams' => true,
            ),
            'strip_whitespaces' => array(
                'type' => 'pattern_replace',
                'pattern' => '\s',
                'replacement' => '',
            ),
            'strip_apostrophes' => array(
                'type' => 'pattern_replace',
                'pattern' => "'",
                'replacement' => '',
            ),
            'edge_ngram_front' => array(
                'type' => 'edgeNGram',
                'min_gram' => 3,
                'max_gram' => 10,
                'side' => 'front',
            ),
            'edge_ngram_back' => array(
                'type' => 'edgeNGram',
                'min_gram' => 3,
                'max_gram' => 10,
                'side' => 'back',
            ),
            'length' => array(
                'type' => 'length',
                'min' => 2,
            ),
        );
        /** @var $helper Bubble_Search_Helper_Data */
        $helper = $this->_getHelper();
        foreach (Mage::app()->getStores() as $store) {
            /** @var $store Mage_Core_Model_Store */
            $languageCode = $helper->getLanguageCodeByStore($store);
            $lang = Zend_Locale_Data::getContent('en_GB', 'language', $helper->getLanguageCodeByStore($store));
            if (!in_array($lang, $this->_snowballLanguages)) {
                continue; // language not present by default in elasticsearch
            }
            $indexSettings['analysis']['analyzer']['analyzer_' . $languageCode] = array(
                'type' => 'custom',
                'tokenizer' => 'standard',
                'filter' => array('length', 'lowercase', 'snowball_' . $languageCode),
            );
            $indexSettings['analysis']['filter']['snowball_' . $languageCode] = array(
                'type' => 'snowball',
                'language' => $lang,
            );
        }

        if ($this->isIcuFoldingEnabled()) {
            foreach ($indexSettings['analysis']['analyzer'] as &$analyzer) {
                array_unshift($analyzer['filter'], 'icu_folding');
            }
            unset($analyzer);
        }

        return $indexSettings;
    }

    /**
     * Retrieves searchable fields according to text query.
     *
     * @param bool $onlyFuzzy
     * @param string $q
     * @return array
     */
    protected function _getSearchFields($onlyFuzzy = false, $q = '')
    {
        $properties = $this->_getIndexProperties();
        $fields = array();
        foreach ($properties as $key => $property) {
            if ($property['type'] == 'date'
                || ($property['type'] == 'multi_field' && $property['fields'][$key]['type'] == 'date')) {
                continue;
            }
            if (!is_bool($q)
                && ($property['type'] == 'boolean'
                    || ($property['type'] == 'multi_field' && $property['fields'][$key]['type'] == 'boolean'))) {
                continue;
            }
            if (!is_integer($q)
                && ($property['type'] == 'integer'
                    || ($property['type'] == 'multi_field' && $property['fields'][$key]['type'] == 'integer'))) {
                continue;
            }
            if (!is_double($q)
                && ($property['type'] == 'double'
                    || ($property['type'] == 'multi_field' && $property['fields'][$key]['type'] == 'double'))) {
                continue;
            }
            if (!$onlyFuzzy && $property['type'] == 'multi_field') {
                foreach (array_keys($property['fields']) as $field) {
                    if ($field != 'untouched') {
                        $fields[] = $key . '.' . $field;
                    }
                }
            } elseif (0 !== strpos($key, 'sort_by_')) {
                $fields[] = $key;
            }
        }

        if ($this->_getHelper()->shouldSearchOnOptions()) {
            // Search on options labels too
            $fields[] = '_options';
        }

        return $fields;
    }

    /**
     * Checks if attribute is indexable.
     *
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute
     * @return bool
     */
    protected function _isAttributeIndexable($attribute)
    {
        return $this->_getHelper()->isAttributeIndexable($attribute);
    }

    /**
     * Creates or updates Elasticsearch index.
     *
     * @link http://www.elasticsearch.org/guide/reference/mapping/core-types.html
     * @link http://www.elasticsearch.org/guide/reference/mapping/multi-field-type.html
     * @return Bubble_Search_Model_Resource_Engine_Elasticsearch_Client
     * @throws Exception
     */
    protected function _prepareIndex()
    {
        try {
            $indexSettings = $this->_getIndexSettings();
            $index = $this->getIndex($this->_index);
            if (!$index->exists()) {
                $indexSettings['number_of_shards'] = (int) $this->getConfig('number_of_shards');
                $index->create($indexSettings);
            } else {
                $index->setSettings($indexSettings);
            }
            $mapping = new Elastica_Type_Mapping();
            $mapping->setType($index->getType('product'));
            $mapping->setProperties($this->_getIndexProperties());
            $mapping->send();
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            throw $e;
        }

        return $this;
    }
}