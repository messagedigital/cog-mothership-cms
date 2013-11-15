<?php

namespace Message\Mothership\CMS\Field\Type;

use Message\Mothership\CMS\Field;
use Message\Mothership\CMS\Page\Page;

use Message\Cog\Form\Handler;
use Message\Cog\Service\ContainerInterface;
use Message\Cog\Service\ContainerAwareInterface;

/**
 * A field for a link to an internal or external page.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class Productoption extends Field\MultipleValueField implements ContainerAwareInterface
{
	protected $_services;

	/**
	 * {@inheritdoc}
	 */
	public function setContainer(ContainerInterface $container)
	{
		$this->_services = $container;
	}

	public function getFormField(Handler $form)
	{
		$names  = $this->_services['product.option.loader']->getAllOptionNames();
		$values = $this->_services['product.option.loader']->getAllOptionValues();
		$field  = new Field\FormType\LinkedChoice(array(
			'name'  => array_combine($names, $names),
			'value' => array_combine($values, $values),
		));

		$form->add($this->getName(), $field, $this->getLabel(), array(
			'attr' => array('data-help-key' => $this->_getHelpKeys()),
		))->val()->optional();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getValueKeys()
	{
		return array(
			'name',
			'value',
		);
	}
}