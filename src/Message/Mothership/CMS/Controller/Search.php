<?php

namespace Message\Mothership\CMS\Controller;

use InvalidArgumentException;
use Message\Cog\Controller\Controller;
use Message\Mothership\CMS\SearchLog\SearchLog;

/**
 * Search controller for pages.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Search extends Controller {

	/**
	 * View the search results based against a set of terms.
	 *
	 * @return Response
	 */
	public function view()
	{
		$termsString = $this->get('request')->get('terms');

		if (! $termsString or empty($termsString)) {
			throw $this->getNotFoundException('You must enter a term for which to search.');
		}

		// Split terms into an array on spaces & commas.
		$terms = preg_split("/[\s,]+/", $termsString);

		// Get the current page, default to first.
		$page = ($this->get('request')->get('page')) ?: 1;

		$pages = $this->get('cms.page.loader')->getBySearchTerms($terms);

		// Slice the results to get the current page.
		// $pages = array_slice($pages, ($page - 1) * $perPage, $perPage);

		// Log search request.
		$searchLog            = new SearchLog;
		$searchLog->term      = $termsString;
		$searchLog->referrer  = $this->get('request')->server->get('REFERER');
		$searchLog->ipAddress = $this->get('request')->getClientIp();
		$this->get('cms.search.create')->create($searchLog);

		return $this->render('::search:listing', array(
			'termsString' => $termsString,
			'pages' => $pages,
			'pagination' => null
		));
	}

	/**
	 * Get the search form.
	 *
	 * @return [type] [description]
	 */
	public function _form()
	{
		$form = $this->get('form')
					 ->setMethod('GET')
					 ->setAction($this->generateUrl('ms.cms.search'));

		$form->add('terms', 'text', 'Search');

		return $form;
	}

}