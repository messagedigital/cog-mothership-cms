<?php

namespace Message\Mothership\CMS\Field\Type;

use Message\Mothership\CMS\Field\Field;

use Message\Mothership\FileManager\File\Type as FileType;

/**
 * A field for a file in the file manager database.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class File extends Field
{
	protected $_allowedTypes;

	public function getFormField($form)
	{
		$form->add($this->getName(), 'file', $this->getLabel(), array(
			'attr' => array('data-translation-key' => $this->_translationKey)
		));
	}

	public function setAllowedTypes($types)
	{
		if (!is_array($types)) {
			$types = array($types);
		}

		$this->_allowedTypes = $types;

		return $this;
	}
}