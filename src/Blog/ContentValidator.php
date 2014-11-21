<?php

namespace Message\Mothership\CMS\Blog;

use Message\Mothership\CMS\Page\Content;

use Message\Cog\Field;

/**
 * Class ContentValidator
 * @package Message\Mothership\CMS\Blog
 *
 * @author Thomas Marchant <thomas@message.co.uk>
 *
 * Class for validating that the Content object on a page has all the necessary fields to process a comment
 */
class ContentValidator
{
	/**
	 * Determine if a Content object has all the requisite fields needed to process comments
	 *
	 * @param Content $content
	 *
	 * @return bool
	 */
	public function isValid(Content $content)
	{
		try {
			$this->validate($content);
		} catch (InvalidContentException $e) {
			return false;
		}

		return true;
	}

	/**
	 * Validate comments Content group
	 *
	 * @param Content $content
	 * @throws InvalidContentException
	 */
	public function validate(Content $content)
	{
		$commentsGroup = $content->{ContentOptions::COMMENTS};
		if (null === $commentsGroup) {
			throw new InvalidContentException('`comments` group not declared on Content object');
		}

		if (!$commentsGroup instanceof Field\Group) {
			throw new InvalidContentException(
				'`' . ContentOptions::COMMENTS . '` must be a content group, instance of ' .
				(gettype($commentsGroup) === 'object' ? get_class($commentsGroup) : gettype($commentsGroup)) .
				' given'
			);
		}

		$this->_validateEnablingOptions($commentsGroup);
		$this->_validateAccessOptions($commentsGroup);
	}

	/**
	 * Validate that all the necessary enabling option fields are present and correct on comments content group.
	 * In this instance, 'enabling options' refers to whether or not comments are enabled on a page, and whether they
	 * require approval.
	 *
	 * @param Field\Group $commentsGroup
	 * @throws InvalidContentException
	 */
	private function _validateEnablingOptions(Field\Group $commentsGroup)
	{
		if (null === $commentsGroup->{ContentOptions::ALLOW_COMMENTS}) {
			throw new InvalidContentException('Option for `' . ContentOptions::ALLOW_COMMENTS . '` not defined');
		}

		$allowComments = $commentsGroup->{ContentOptions::ALLOW_COMMENTS};

		if (!$allowComments instanceof Field\Field) {
			throw new InvalidContentException('`' . ContentOptions::ALLOW_COMMENTS . '` must be an instance of Message\\Cog\\Field\\Field');
		}

		if ($allowComments->getValue() === ContentOptions::DISABLED) {
			throw new InvalidContentException('Comments are disabled for this page');
		}

		if ($allowComments->getValue() !== ContentOptions::ALLOW && $allowComments->getValue() !== ContentOptions::APPROVE) {
			throw new InvalidContentException('Comment setting `' . $allowComments->getValue() . '` is invalid');
		}
	}

	/**
	 * Validate that all the access options are present and correct on comments content group.
	 * In this instance, 'access options' refers to which users can leave comments on a page.
	 *
	 * @param Field\Group $comments
	 * @throws InvalidContentException
	 */
	private function _validateAccessOptions(Field\Group $comments)
	{
		if (null === $comments->{ContentOptions::PERMISSION}) {
			throw new InvalidContentException('Option for `' . ContentOptions::PERMISSION . '` not set');
		}

		$permission = $comments->{ContentOptions::PERMISSION};

		if (!$permission instanceof Field\MultipleValueField) {
			throw new InvalidContentException('`' . ContentOptions::PERMISSION . '` field must be an instance of Message\\Cog\\Field\\MultipleValueField');
		}

		$permissionValue = $permission->getValue();

		if (empty($permissionValue)) {
			throw new InvalidContentException('`' . ContentOptions::PERMISSION . '` is not determined');
		}
	}
}