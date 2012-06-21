<?php

class Elastica_Facet_Query extends Elastica_Facet_Abstract
{
	public function toArray() {
		$this->_setFacetParam('query', $this->_params);
		return parent::toArray();
	}
}
