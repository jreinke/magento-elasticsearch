## Enhance your Magento store search results using Elasticsearch

[![Build Status](https://secure.travis-ci.org/jreinke/magento-elasticsearch.png?branch=master)](http://travis-ci.org/jreinke/magento-elasticsearch)

![Magento Elasticsearch](http://i.imgur.com/X6quR.png)

## Installation

### Magento CE 1.7+ only

Install with [modgit](https://github.com/jreinke/modgit):

    $ cd /path/to/magento
    $ modgit init
    $ modgit clone elasticsearch https://github.com/jreinke/magento-elasticsearch.git

or download package manually:

* Download latest version [here](https://github.com/jreinke/magento-elasticsearch/archive/master.zip)
* Unzip in Magento root folder
* Clean cache

## Usage

* Go to System > Configuration > Catalog > Catalog Search
* Select Elasticsearch search engine
* Configure server connection parameters
* Specify index name (default is magento)
* Optionally defines your custom search parameters
* Optionally boost some product attributes in Catalog > Attributes > Manage Attributes
* Reindex catalog search index
