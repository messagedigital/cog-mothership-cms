<?php

namespace Message\Mothership\CMS\Page;

use Message\Mothership\CMS\PageType\PageTypeInterface;
use Message\Mothership\CMS\PageType\Collection as PageTypeCollection;

use Message\User\Group\Collection as UserGroupCollection;
use Message\User\UserInterface;

use Message\Cog\ValueObject\DateRange;
use Message\Cog\ValueObject\Authorship;
use Message\Mothership\CMS\PageType\Collection;
use Message\Cog\ValueObject\DateTimeImmutable;
use Message\Cog\ValueObject\Slug;
use Message\Cog\DB\Result;
use Message\Cog\Pagination\Pagination;
use Message\Cog\DB\Entity\EntityLoaderCollection;
use Message\Cog\DB\QueryBuilderFactory;

/**
 * Responsible for loading page data and returning prepared instances of `Page`.
 *
 * Usage as follows:
 *
 * <code>
 * # Load by pageID
 * $loader = new Loader($locale, $query);
 * $page = $loader->getByID(1); // returns page object
 *
 * # Load deleted page - deleted pages are not loaded by default
 * $page = $loader->getByID(3); // returns false as deleted so do the following
 * $page = $loader->includeDeleted(true)->getByID(3); // this will now return the deleted page object
 *
 * # Load by slug
 * // you can use either a slug object or a string
 * $slug = new Slug('/blog/hello-world');
 * $page = $loader->getBySlug($slug); // returns page object
 *
 * # You can then load that pages siblings, or the children
 * $siblings = $loader->getSiblings($page); // returns array of Page objects
 * $children = $loader->getChildren($page); // returns array of Page objects
 * $children = $loader->includeDeleted(true)->getChildren($page); // returns array of Page objects inc deleted pages
 *
 * # You can also load by type
 * $pages = $loader->getByType(new PageType\Blog); // returns array of page types
 * </code>
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 * @author Danny Hannah <danny@message.co.uk>
 */
class Loader
{
	protected $_locale;
	protected $_queryBuilderFactory;
	protected $_pageTypes;
	protected $_authorisation;
	protected $_loaders;

	protected $_pagination;

	/**
	 * var to toggle the loading of deleted pages
	 *
	 * (default value: false)
	 *
	 * @var bool
	 */
	protected $_loadDeleted     = false;

	/**
	 * var to toggle the loading of unpublished pages
	 *
	 * @var boolean
	 */
	protected $_loadUnpublished = true;
	protected $_loadUnviewable  = true;

	/**
	 * The order in which to load pages
	 * 
	 * @var string
	 */
	private $_order = PageOrder::STANDARD;

	/**
	 * Constructor
	 *
	 * @param \Locale             $locale    		The current locale
	 * @param Query               $query     		Database query instance to use
	 * @param PageTypeCollection  $pageTypes 		Page types available to the system
	 * @param UserGroupCollection $groups    		User groups available to the system
	 * @param Authorisation  	  $authorisation 	Authorisation instance to use
	 */
	public function __construct(/* \Locale */ $locale,
		QueryBuilderFactory $queryBuilderFactory,
		PageTypeCollection $pageTypes,
		UserGroupCollection $groups,
		Authorisation $authorisation,
		UserInterface $user,
		Searcher $searcher,
		EntityLoaderCollection $loaders
	) {
		$this->_locale              = $locale;
		$this->_queryBuilderFactory = $queryBuilderFactory;
		$this->_pageTypes           = $pageTypes;
		$this->_userGroups          = $groups;
		$this->_authorisation       = $authorisation;
		$this->_user 		        = $user;
		$this->_searcher            = $searcher;
		$this->_loaders             = $loaders;
	}

	/**
	 * Get page(s) by ID.
	 *
	 * If an array of page IDs is passed, an array of the prepared `Page`
	 * instances is returned where the keys are the page IDs.
	 *
	 * @param int|array $pageIDs Page ID or array of page IDs to load
	 *
	 * @return Page|array[Page]   Prepared `Page` instance(s)
	 */
	public function getByID($pageIDs)
	{
		$this->_returnAsArray = is_array($pageIDs);

		return $this->_load($pageIDs);
	}

	/**
	 * retrive the homepage by getting the left most and top level node in the
	 * tree that is avaialble and not marked as deleted
	 *
	 * @return Page|false 		Page object of homepage to use
	 */
	public function getHomepage()
	{
		$qb = $this->_getBaseQuery();

		$qb
			->where('page.deleted_at IS NULL')
			->where('page.position_depth = 0')
			->orderBy('position_left')
			->limit(1)
		;

		$pages = $this->_load($qb);

		return count($result) ? $this->getByID($result->first()->page_id) : false;
	}

	/**
	 * Get a page by its slug.
	 *
	 * @param string  $slug		 	The slug to check for
	 * @param boolean $checkHistory True to check through historical slug data
	 *
	 * @return Page|false			Prepared `Page` instance, or false if not found
	 */
	public function getBySlug($slug, $checkHistory = true)
	{
		if ($slug == '/') {
			return $this->getHomepage();
		}

		// Clean up the slug
		$path 	= trim($slug, '/');
		// Turn it into an array and reverse it.
		$parts 	= array_reverse(explode('/', $path));
		$base 	= array_shift($parts);

		$joins 	= '';
		$where 	= '';
		$params = array(
			$base,
			count($parts),
		);

		// Loop thorough the parts of the url and build joins in order to get
		// all the parent slugs so we can build the full slug because we do not
		// store the full slug in the db
		for ($i = 2; $i <= count($parts) +1; $i++) {
			$joins.= " JOIN page level$i ON (level$i.position_left < level".($i-1).".position_left AND level$i.position_right > level".($i-1).".position_right)";
			$where.= " AND level$i.slug = ?s";
			$where.= " AND level$i.deleted_at IS NULL";
			$params[] = $parts[$i-2];
		}

		// Run the query and add in the joins we made above
		$result = $this->_queryBuilderFactory->run('BuilderFactory
			SELECT
				level1.page_id
			FROM
				page level1
			'.$joins.'
			WHERE
				level1.slug = ?s
				AND level1.position_depth = ?i
				AND level1.deleted_at IS NULL
			'.$where.'',
			$params
		);

		// If there is a result then retun a page object
		if (count($result)) {
			return $this->getByID($result->first()->page_id);
		}
		// If no result has been returned at this point and $checkHistory is true
		// then we will check the history to see if it existed in the past
		if ($checkHistory && $page = $this->checkSlugHistory($slug)) {
			return $page;
		}

		return false;
	}

	public function getParent(Page $page)
	{
		$result = $this->_queryBuilderFactory->run('BuilderFactory
			SELECT
				page_id
			FROM
				page
			WHERE
				position_left < ?i
			AND position_right >= ?i
			AND position_depth = ?i -1',
		array(
			$page->left,
			$page->left,
			$page->depth,
		));

		return count($result) ? $this->getByID($result->value()) : false;
	}

	/**
	 * Get the root page for a given page.
	 *
	 * If the given page has a depth of 0, it is the root page and is returned
	 * immediately without any database loading.
	 *
	 * Otherwise, the top-level root page for this page is loaded and returned.
	 *
	 * @param  Page   $page The page to search up from
	 *
	 * @return Page|false   The top-level (root) page
	 */
	public function getRoot(Page $page)
	{
		if (0 == $page->depth) {
			return $page;
		}

		$result = $this->_queryBuilderFactory->run('BuilderFactory
			SELECT
				page_id
			FROM
				page
			WHERE
				position_left < :left?i
			AND position_right >= :right?i
			AND position_depth = 0
		', array(
			'left'  => $page->left,
			'right' => $page->right,
		));

		return count($result) ? $this->getByID($result->value()) : false;
	}


	/**
	 * Find a page by an ancestral slug
	 *
	 * @param string $slug		Slug to check for
	 * @return Page|false		Prepared `Page` instance, or false if not found
	 */
	public function checkSlugHistory($slug)
	{
		$slug = '/' . ltrim($slug, '/');

		$result = $this->_queryBuilderFactory->run('BuilderFactory
			SELECT
				page_id
			FROM
				page_slug_history
			WHERE
				slug = ?s
		', $slug);

		return count($result) ? $this->getByID($result->value()) : false;
	}

	/**
	 * Get all pages of a specific type.
	 *
	 * @param PageTypeInterface|string $pageType The page type or page type name
	 *                                           to get pages for
	 *
	 * @return array[Page]                       An array of prepared `Page`
	 *                                           instances
	 */
	public function getByType($pageType)
	{
		if ($pageType instanceof PageTypeInterface) {
			$pageType = $pageType->getName();
		}

		$result = $this->_queryBuilderFactory->run('BuilderFactory
			SELECT
				page_id
			FROM
				page
			WHERE
				type = ?s
		', strtolower($pageType));

		return count($result) ? $this->getById($result->flatten()) : false;
	}

	/**
	 * Select a page that has a certain tag
	 *
	 * @param $tag
	 * @return array | bool |Page
	 */
	public function getByTag($tag)
	{
		$result = $this->_queryBuilderFactory->run('BuilderFactory
			SELECT
				page_id
			FROM
				page_tag
			WHERE
				tag_name = :tag?s
		', [
			'tag' => $tag
		]);

		return count($result) ? $this->getByID($result->flatten()) : false;
	}

	/**
	 * Get pages that match a set of terms, ordered by score.
	 *
	 * @param  array  $terms   Terms to search
	 * @param  int    $page    Current page
	 * @param  array  $options Various options
	 *
	 * @return array[Page]
	 */
	public function getBySearchTerms($terms, $page = 1, $options = array())
	{
		if (!empty($options['min_length']) && is_int($options['min_length'])) {
			$this->_searcher->setMinTermLength($options['min_length']);
		}
		elseif (!empty($options['min_length'])) {
			throw new \InvalidArgumentException('`min_length` option must be an integer!');
		}

		$this->_searcher->setTerms($terms);

		$ids = $this->_searcher->getIds();

		if (empty($ids)) {
			return array();
		}

		$results = $this->getById($ids);

		// Check authorisation restrictions on pages.
		foreach ($results as $i => $page) {
			if (false === $this->_authorisation->isViewable($page, $this->_user) or
				false === $this->_authorisation->isPublished($page)
			) {
				unset($results[$i]);
			}
		}

		return $this->_searcher->getSorted($results);
	}

	/**
	 * Return all files in an array
	 * @return Array|File|false - 	returns either an array of File objects, a
	 * 								single file object or false
	 */
	public function getAll()
	{
		$result = $this->_queryBuilderFactory->run('BuilderFactory
			SELECT
				page_id
			FROM
				page
		');

		return count($result) ? $this->getByID($result->flatten()) : false;
	}

	public function getTopLevel()
	{
		return $this->getChildren(null);
	}

	/**
	 * Get the child pages for a page.
	 *
	 * @param Page|null $page The page to find the children for
	 *
	 * @return array[Page]	 An array of prepared `Page` instances
	 */
	public function getChildren(Page $page = null)
	{
		if (!$page) {
			$page = new Page;
			$page->left = 0;
			$page->right = 99999;
			$page->depth = -1;
		}

		$result = $this->_queryBuilderFactory->run("BuilderFactory
			SELECT
				page_id
			FROM
				page
			WHERE
				position_left > ?i
			AND
				position_right < ?i
			AND
				position_depth = ?i
		", array(
			$page->left,
			$page->right,
			$page->depth+1,
		));

		return count($result) ? $this->getById($result->flatten()) : array();
	}

	public function setPagination(Pagination $pagination)
	{
		$this->_pagination = $pagination;

		return $this;
	}

	/**
	 * Get the siblings (pages at the same level in the IA) for a page.
	 *
	 * @param Page $page The page to find the siblings for
	 *
	 * @return array[Page]	 An array of prepared `Page` instances
	 */
	public function getSiblings(Page $page, $includeRequestPage = false)
	{
		// We have to do a different query if the depth is zero, as this causes
		// complications and have to change the query a fair bit. This way is simpler.
		if ($page->depth == 0) {
			$result = $this->_queryBuilderFactory->run('BuilderFactory
			    SELECT
			        page.page_id
			    FROM
			        page
			    WHERE
			    	page.position_depth = 0
				AND
					page.page_id <> ?i
				' . $this->_getOrderQuery()
			, array(
			    $page->id,
			));

		} else {
			// Get the parent, then get the children of that parent based on it's position.
			$result = $this->_queryBuilderFactory->run('BuilderFactory
			    SELECT
			        children.page_id
			    FROM
			        page AS parent
			    LEFT JOIN page AS children
			        ON (
			        	children.position_left > parent.position_left
			        	AND children.position_right < parent.position_right
			        	AND children.position_depth = ?i
			        )

			    WHERE
			        parent.position_left < ?i
			        AND parent.position_right > ?i
			        AND parent.position_depth = ?i
					AND children.page_id <> ?i'
			, array(
			    $page->depth,
			    $page->left,
			    $page->right,
			    $page->depth -1,
			    $page->id,
			));
		}

		if (0 === count($result)) {
			return ($includeRequestPage) ? array($page->id => $page) : array();
		}

		$pages = $this->getById($result->flatten());

		if ($includeRequestPage) {
			$pages[$page->id] = $page;
		}

		return $pages;
	}


	/**
	 * Toggle whether or not to load deleted pages
	 *
	 * @param bool $bool 	true / false as to whether to include deleted items
	 * @return 	$this 		Loader object in order to chain the methods
	 */
	public function includeDeleted($bool)
	{
		$this->_loadDeleted = $bool;
		return $this;
	}

	/**
	 * Toggle whether or not to load pages which are unpublshied
	 *
	 * @param  bool $bool true / false as to whether to load unpublished pages
	 *
	 * @return $this       Loader object in order to chain methods
	 */
	public function includeUnpublished($bool)
	{
		$this->_loadUnpublished = $bool;
		return $this;
	}

	/**
	 * Load all the given pages and pass the results onto the _loadPage method
	 *
	 * @param int|array		$pageID id of the page to load
	 * @return Page|false 			populated Page object or array of Page
	 *                              objects or false if not found
	 */
	protected function _load($qb)
	{


		if (null !== $this->_pagination) {
			$this->_pagination->setCountQuery('SELECT COUNT(p.id) as `count` FROM (' . $sql . ') as p', $params);
			$this->_pagination->setQuery($sql, $params);
			$result = $this->_pagination->getCurrentPageResults();
			$this->_pagination = null;
		}
		else {
			$result = $this->_queryBuilderFactory->run($BuilderFactorysql, $params);
		}

		if (0 === count($result)) {
			return ($this->_returnAsArray) ? array() : false;
		}

		return $this->_loadPage($result);
	}
	
	protected function _getBaseQuery()
	{
		$qb = $this->_queryBuilderFactory->getQueryBuilder();

		$qb
			->select('page.page_id AS id')
			->select('page.title AS title')
			->select('page.type AS type')
			->select('page.publish_at AS publishAt')
			->select('page.unpublish_at AS unpublishAt')
			->select('page.created_at AS createdAt')
			->select('page.created_by AS createdBy')
			->select('page.updated_at AS updatedAt')
			->select('page.created_by AS updatedBy')
			->select('page.deleted_at AS deletedAt')
			->select('page.deleted_by AS deletedBy')
			->select('IFNULL(CONCAT((
					SELECT
						CONCAT(\'/\',GROUP_CONCAT(p.slug ORDER BY p.position_depth ASC SEPARATOR \'/\'))
					FROM
						page AS p
					WHERE
						p.position_left < page.position_left
					AND
						p.position_right > page.position_right),\'/\',page.slug),page.slug) AS slug,')
			->select('page.position_left AS `left`,')
			->select('page.position_right AS `right`,')
			->select('page.position_depth AS depth,')
			->select('page.meta_title AS metaTitle,')
			->select('page.meta_description AS metaDescription,')
			->select('page.meta_html_head AS metaHtmlHead,')
			->select('page.meta_html_foot AS metaHtmlFoot,')
			->select('page.visibility_search AS visibilitySearch,')
			->select('page.visibility_menu AS visibilityMenu,')
			->select('page.visibility_aggregator AS visibilityAggregator,')
			->select('page.password AS password,')
			->select('page.access AS access,')
			->select('GROUP_CONCAT(page_access_group.group_name SEPARATOR \',\') AS accessGroups')

			->from('page')

			->leftJoin('page_access_group', 'page_access_group.page_id = page.page_id')
		;

		if (!$this->_loadUnpublished) {
			$qb->where('
				(page.unpublish_at IS NULL AND page.publish_at IS NOT NULL 
					OR 
				page.publish_at > page.unpublish_at)');
		}

		if (!$this->_loadDeleted) {
			$qb->where('
				(page.deleted_at IS NULL AND page.created_at IS NOT NULL 
					OR 
					page.created_at > page.deleted_at)');
		}

		$qb->groupBy('page.page_id');

		$this->_orderQuery($qb);

		return $qb;
	}

	/**
	 * Load the results into instances of `Page` and return them.
	 *
	 * @param  Result $results  Database result of page load query
	 *
	 * @return Page|array[Page] Singular Page object, or array of page objects
	 */
	protected function _loadPage(Result $results)
	{
		$pages = $results->bindTo(
			'Message\\Mothership\\CMS\\Page\\PageProxy',
			[$this->_loaders]
		);

		foreach ($results as $key => $data) {
			// Skip deleted pages
			if ($data->deletedAt && !$this->_loadDeleted) {
				unset($pages[$key]);
				continue;
			}

			$pages[$key]->visibilitySearch     = (bool) $pages[$key]->visibilitySearch;
			$pages[$key]->visibilityMenu       = (bool) $pages[$key]->visibilityMenu;
			$pages[$key]->visibilityAggregator = (bool) $pages[$key]->visibilityAggregator;

			// Load the DateRange object for publishDateRange
			$pages[$key]->publishDateRange = new DateRange(
				$data->publishAt   ? new DateTimeImmutable(date('c', $data->publishAt))   : null,
				$data->unpublishAt ? new DateTimeImmutable(date('c', $data->unpublishAt)) : null
			);

			// Remove the page if we are asking to not show unpublished pages and
			// the page is in fact unpublished
			if (!$this->_loadUnpublished && !$this->_authorisation->isPublished($pages[$key])) {
				unset($pages[$key]);
				continue;
			}

			// Get the page type
			$pages[$key]->type = $this->_pageTypes->get($data->type);


			// If the page is the most left page then it is the homepage so
			// we need to override the slug to avoid unnecessary redirects
			if ($this->_getMinPositionLeft() == $data->left) {
				$data->slug = new Slug('/');
			}

			$pages[$key]->slug = new Slug($data->slug);
			$pages[$key]->type = clone $this->_pageTypes->get($data->type);

			// Load authorship details
			$pages[$key]->authorship = new Authorship;
			$pages[$key]->authorship->create(new DateTimeImmutable(date('c',$data->createdAt)), $data->createdBy);

			if ($data->updatedAt) {
				$pages[$key]->authorship->update(new DateTimeImmutable(date('c',$data->updatedAt)), $data->updatedBy);
			}

			if ($data->deletedAt) {
				$pages[$key]->authorship->delete(new DateTimeImmutable(date('c',$data->deletedAt)), $data->deletedBy);
			}

			// If the page is set to inherit it's access then loop through each
			// parent to find the inherited access level.
			$pages[$key]->accessInherited = false;
			$check = $pages[$key];

			while ($pages[$key]->access < 0) {
				$check = $this->_queryBuilderFactory->run('BuilderFactory
					SELECT
						access,
						GROUP_CONCAT(page_access_group.group_name SEPARATOR \',\') AS accessGroups
					FROM
						page
					LEFT JOIN
						page_access_group ON (page_access_group.page_id = page.page_id)
					WHERE
						position_left < ?i
					AND position_right >= ?i
					AND position_depth = ?i -1',
				array(
					$check->left,
					$check->left,
					$check->depth,
				));

				$check = $check->bindTo('Message\\Mothership\\CMS\\Page\\Page');
				$check = $check[0];

				$pages[$key]->access = $check->access;
				$data->accessGroups = $check->accessGroups;

				$pages[$key]->accessInherited = true;
			}

			// Ensure the page access is at least 0
			$pages[$key]->access = max(0, $pages[$key]->access);

			// Load access groups
			$groups = array_filter(explode(',', $data->accessGroups));
			$pages[$key]->accessGroups = array();
			foreach ($groups as $groupName) {
				if ($group = $this->_userGroups->get(trim($groupName))) {
					$pages[$key]->accessGroups[$group->getName()] = $group;
				}
			}

			if (!$this->_loadUnviewable && $this->_authorisation->isViewable($pages[$key], $this->_user)) {
				unset($pages[$key]);
				continue;
			}

		}

		return count($pages) == 1 && !$this->_returnAsArray ? $pages[0] : $pages;
	}

	private function _getMinPositionLeft()
	{
		return $this->_queryBuilderFactory->run('BuilderFactory
				SELECT MIN(`position_left`) FROM `page` WHERE deleted_at IS NULL
			')->value();
	}

	protected function _orderQuery(QueryBuilderFactory $qb)
	{
		if ($this->_order instanceof PageOrderStatement\PageOrderStatementInterface) {
			$this->_order($qb);
			return;
		}

		switch ($this->_order) {
			case PageOrder::ID:
				$qb->orderBy('`page`.`page_id` ASC');
				return;
			case PageOrder::ID_REVERSE:
				$qb->orderBy('`page`.`page_id` DESC');
				return;

			case PageOrder::UPDATED_DATE:
				$qb->orderBy('`page`.`updated_at` ASC');
				return;
			case PageOrder::UPDATED_DATE_REVERSE:
				$qb->orderBy('`page`.`updated_at` DESC');
				return;

			case PageOrder::CREATED_DATE:
				$qb->orderBy('`page`.`created_at` ASC');
				return;
			case PageOrder::CREATED_DATE_REVERSE:
				$qb->orderBy('`page`.`created_at` DESC');
				return;
				
			case PageOrder::REVERSE:
				$qb->orderBy('`page`.`position_left` DESC');
				return;
			case PageOrder::STANDARD:
			default:
				$qb->orderBy('`page`.`position_left` ASC');
				return;
		}
	}


	/**
	 * set the ordering, use the order constants
	 * 
	 * @param string the ordering
	 */
	public function orderBy(/* string | QueryBuilderFactor */ $order)
	{
		$this->_order = $order;

		return $this;
	}
}