<?php

namespace Message\Mothership\CMS\Page;

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
	public $publishState;
	public $publishDateRange;
	public $slug;

	public $left;
	public $right;
	public $depth;

	public $metaTitle;
	public $metaDescription;
	public $metaHtmlHead;
	public $metaHtmlFoot;
	// do we need to store meta _inherit flags?

	public $visibilitySearch;
	public $visibilityMenu;
	public $visibilityAggregator;

	public $password;
	public $access;
	public $accessGroups;

	public $commentsEnabled;
	public $commentsAccess;
	public $commentsAccessGroups;
	public $commentsApproval;
	public $commentsExpiry;

	public function getType()
	{
		return $this->type;
	}
}