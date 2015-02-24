<?php

namespace Message\Mothership\CMS\Page;

use Message\Cog\Service\Container;
use Message\Cog\ValueObject\DateRange;
use Message\Cog\ValueObject\DateTimeImmutable;

/**
 * Represents the properties of a single page.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 *
 * @todo figure out locales - the PHP locale object is not what I hoped it was
 */
class Page
{
	public $locale;
	public $authorship;

	public $id;
	public $title;
	public $type;
	public $publishDateRange;
	public $slug;
	// public to avoid BC break for now
	public $tags = [];

	public $left;
	public $right;
	public $depth;

	public $metaTitle;
	public $metaDescription;
	public $metaHtmlHead;
	public $metaHtmlFoot;

	public $metaTitleInherit;
	public $metaDescriptionInherit;
	public $metaHtmlHeadInherit;
	public $metaHtmlFootInherit;

	public $visibilitySearch;
	public $visibilityMenu;
	public $visibilityAggregator;

	public $password;
	public $access;
	public $accessGroups = array();

	protected $_content;

	public function getType()
	{
		return $this->type;
	}

	/**
	 * Load & return the content for this page.
	 *
	 * This is not the preferred way of doing this, it is better to use the
	 * `ContentLoader` directly within the controller. However, sometimes this
	 * method makes view building for listings far less complex.
	 *
	 * @todo Figure out a way to not statically call the service container to
	 *       adhere to our coding standards.
	 *
	 * @return Content
	 */
	public function getContent()
	{
		if (!$this->_content) {
			$this->_content = Container::get('cms.page.content_loader')->load($this);
		}

		return $this->_content;
	}

	public function getTags()
	{
		return $this->tags;
	}

	public function setTags($tags)
	{
		$this->tags = $tags;

		return $this;
	}

	public function setPublished($publish = true)
	{
		if ($publish) {
			$this->publishDateRange = new DateRange(
				new DateTimeImmutable()
			);
		}
		else {
			$this->publishDateRange = new DateRange(
				new DateTimeImmutable(),
				new DateTimeImmutable()
			);
		}
	}

	/**
	 * Check if the page is the homepage.
	 *
	 * @return boolean
	 */
	public function isHomepage()
	{
		return '/' === (string) $this->slug;
	}

	/**
	 * Check to see if the page has any child pages
	 *
	 * @return boolean
	 */
	public function hasChildren()
	{
		return ($this->right - $this->left) > 1;
	}

	/**
	 * Method to check if a page has a certain tag
	 *
	 * @param string $tag     The tag you want to check for
	 *
	 * @return bool
	 */
	public function hasTag($tag)
	{
		return in_array($tag, $this->tags);
	}
}