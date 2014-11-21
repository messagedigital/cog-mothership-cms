<?php

namespace Message\Mothership\CMS\Blog;

/**
 * Class InvalidContentException
 * @package Message\Mothership\CMS\Blog
 *
 * @author Thomas Marchant <thomas@message.co.uk>
 *
 * Exception class to be thrown when the Content object does not have the necessary fields or options to be displaying
 * comments
 */
class InvalidContentException extends \LogicException
{

}