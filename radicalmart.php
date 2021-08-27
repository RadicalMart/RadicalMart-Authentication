<?php
/*
 * @package     RadicalMart Express Package
 * @subpackage  plg_button_radicalmart_express
 * @version     1.0.0
 * @author      Delo Design - delo-design.ru
 * @copyright   Copyright (c) 2021 Delo Design. All rights reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link        https://delo-design.ru/
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;

class plgAuthenticationRadicalMart extends CMSPlugin
{
	/**
	 * Affects constructor behavior.
	 *
	 * @var  boolean
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected $autoloadLanguage = true;

	/**
	 * The name of the plugin.
	 *
	 * @var string
	 *
	 * @since __DEPLOY_VERSION__
	 */
	public $name = 'Radicalmart';

	/**
	 * This method should handle any authentication and report back to the subject.
	 *
	 * @param   array    Array holding the user credentials.
	 * @param   array    Array of extra options.
	 * @param   object   Authentication response object.
	 *
	 * @return  bool True on success, false on failure.
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function onUserAuthenticate(&$credentials, $options, &$response)
	{
		// Check password
		if (empty($credentials['password']))
		{
			$response->status        = JAuthentication::STATUS_FAILURE;
			$response->error_message = Text::_('JGLOBAL_AUTH_EMPTY_PASS_NOT_ALLOWED');

			return false;
		}

		// Check username
		if (empty($credentials['username']))
		{
			$response->status        = JAuthentication::STATUS_FAILURE;
			$response->error_message = Text::_('JGLOBAL_AUTH_NO_USER');

			return false;
		}

		try
		{
			if (!empty(ComponentHelper::getComponent('com_radicalmart_express')->id))
			{
				JLoader::register('RadicalMartHelperUser',
					JPATH_ADMINISTRATOR . '/components/com_radicalmart_express/helpers/user.php');
				JLoader::register('RadicalMartHelperPlugins',
					JPATH_ADMINISTRATOR . '/components/com_radicalmart_express/helpers/plugins.php');
			}
			else
			{
				JLoader::register('RadicalMartHelperUser',
					JPATH_ADMINISTRATOR . '/components/com_radicalmart/helpers/user.php');
				JLoader::register('RadicalMartHelperPlugins',
					JPATH_ADMINISTRATOR . '/components/com_radicalmart/helpers/plugins.php');
			}

			// Prepare data
			$data = array(
				'username' => $credentials['username'],
				'email'    => $credentials['username'],
				'phone'    => RadicalMartHelperUser::cleanPhone($credentials['username']),
			);
			if (!$user = RadicalMartHelperUser::findUser($data)) throw new Exception(Text::_('JGLOBAL_AUTH_NO_USER'));

			// Check password
			$credentials['username'] = $user->username;
			RadicalMartHelperPlugins::triggerPlugin('authentication', 'joomla', 'onUserAuthenticate',
				array($credentials, $options, &$response));

			if ($response->status == JAuthentication::STATUS_FAILURE) throw new Exception($response->error_message);

			return true;
		}
		catch (Exception $e)
		{
			$response->status        = JAuthentication::STATUS_FAILURE;
			$response->error_message = Text::_($e->getMessage());

			return false;
		}
	}
}