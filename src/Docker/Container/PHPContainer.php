<?php
/**
 * @package     Joomla\Testing
 * @subpackage  Docker\Container
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Testing\Docker\Container;

/**
 * PHP Container
 *
 * @since  1.0.0
 */
class PHPContainer extends GenericContainer
{
	/**
	 * Image name
	 *
	 * @var   string
	 *
	 * @since  1.0.0
	 */
	protected $imageName = 'php';
}
