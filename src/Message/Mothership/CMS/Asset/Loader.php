<?php

namespace Message\Mothership\CMS\Asset;

use Message\Cog\DB\Query;

class Loader {
	
	protected $_locale;
	protected $_query;

	/**
	 * var to toggle the loading of deleted assets
	 * 
	 * (default value: false)
	 * 
	 * @var bool
	 */
	protected $_loadDeleted = false;

	public function __construct(/*\Locale*/ $locale, Query $query)
	{
		$this->_locale = $locale;
		$this->_query = $query;
	}

	/**
	 * Return an array of, or singular Asset object
	 * 
	 * @param  int|array $assetID
	 * @return array|asset 	Asset object
	 */
	public function getByID($assetID)
	{
		$return = array();

		if (!is_array($assetID)) {
			return $this->_load($assetID);
		} else {
			foreach ($assetID as $id) {
				$return[] = $this->_load($id);
			}
		}

		return array_filter($return);
	}

	/** 
	 * Returns all the assets of a certain file type id
	 * 
	 * @param  int 	$typeID
	 * @return array|Asset 	Array of Asset objects, or a single Asset object
	 */
	public function getByType($typeID)
	{
		$this->_query->run('
			SELECT
				asset_id
			FROM 
				asset
			WHERE
				type_id = ?i',
			array(
				$typeID,
			)
		);

		return count($result) ? $this->getByID($result->flatten()) : false;

	}

	public function getBySearchTerm($term)
	{
		// We would want to do something clever in here.

	}

	/**
	 * Return all assets in an array
	 * @return Array|Asset|false - 	returns either an array of Asset objects, a 
	 * 								single asset object or false
	 */
	public function getAll()
	{
		$this->_query->run('
			SELECT
				asset_id
			FROM 
				asset
		');

		return count($result) ? $this->getByID($result->flatten()) : false;

	}

	public function getByUnused()
	{

	}

	public function getByUser(\User $user)
	{
		$result = $this->_query->run('
			SELECT
				asset_id
			FROM
				asset
			WHERE
				created_by = ?i',
			array(
				$user->id
			)
		);

		return count($result) ? $this->getByID($result->flatten()) : false;

	}

	public function setSort(\Sorter $sorter)
	{

	}

	public function setPaging(\Pager $pager)
	{

	}

	/**
	 * Toggle whether or not to load deleted assets
	 * 
	 * @param bool $bool 	true / false as to whether to include deleted items
	 * @return 	$this 		Loader object in order to chain the methods
	 */
	public function includeDeleted($bool)
	{
		$this->_loadDeleted = $bool;
		return $this;
	}

	protected function _load($assetID)
	{
		$result = $this->_query->run('
			SELECT
				asset.asset_id AS assetID,
				asset.url AS url,
				asset.name AS name,
				asset.extension AS extension,
				asset.file_size AS fileSize,
				asset.created_at AS createdAt,
				asset.created_by AS createdBy,
				asset.updated_at AS updatedAt,
				asset.updated_by AS updatedBy,
				asset.deleted_at AS deletedAt,
				asset.deleted_by AS deletedBy,
				asset.type_id AS typeID,
				asset.checksum AS checksum,
				asset.preview_url AS previewUrl,
				asset.dimension_x AS dimensionX,
				asset.dimension_y AS dimensionY,
				asset.alt_text AS altText,
				asset.duration AS duration
			FROM 
				asset
			WHERE
				asset.asset_id = ?', array($assetID)
		);

		if (count($result)) {
			$asset = new Asset;
			$asset = $result->bind($asset);
			
			if ($asset->deletedAt && !$this->_loadDeleted) {
				return false;
			}

			$asset->createdAt = new \DateTime(date('c',$asset->createdAt));

			if ($asset->updatedAt) {
				$asset->updatedAt = new \DateTime(date('c',$asset->updatedAt));
			}

			if ($asset->deletedAt) {
				$asset->deletedAt = new \DateTime(date('c',$asset->deletedAt));
			}
			return $asset;
		}

		return false;

	}

}