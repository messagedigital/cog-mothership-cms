<?php

namespace Message\Mothership\CMS\Controller;

class Dashboard extends \Message\Cog\Controller\Controller
{
	public function index()
	{
		return $this->render('::dashboard');
	}
}