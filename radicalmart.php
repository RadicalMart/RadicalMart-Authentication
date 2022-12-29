<?php
/*
 * @package     RadicalMart Express Package
 * @subpackage  plg_button_radicalmart_express
 * @version     __DEPLOY_VERSION__
 * @author      Delo Design - delo-design.ru
 * @copyright   Copyright (c) 2021 Delo Design. All rights reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link        https://delo-design.ru/
 */

defined('_JEXEC') or die;

use Joomla\CMS\Authentication\Authentication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;

class plgAuthenticationRadicalMart extends CMSPlugin
{
	/**
	 * Affects constructor behavior.
	 *
	 * @var  boolean
	 *
	 * @since  1.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * The name of the plugin.
	 *
	 * @var string
	 *
	 * @since 1.0.0
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
	 * @since  1.0.0
	 */
	public function onUserAuthenticate(&$credentials, $options, &$response)
	{
		// Check username
		if (empty($credentials['username']))
		{
			$response->status        = Authentication::STATUS_FAILURE;
			$response->error_message = Text::_('JGLOBAL_AUTH_NO_USER');

			return false;
		}

		try
		{
			$find = false;
			if (!empty(ComponentHelper::getComponent('com_radicalmart')->id))
			{
				$find          = true;
				$userHelper    = '\Joomla\Component\RadicalMart\Administrator\Helper\UserHelper';
				$pluginsHelper = '\Joomla\Component\RadicalMart\Administrator\Helper\PluginsHelper';
			}
			elseif (!empty(ComponentHelper::getComponent('com_radicalmart_express')->id))
			{
				$find = true;
				JLoader::register('RadicalMartHelperUser',
					JPATH_ADMINISTRATOR . '/components/com_radicalmart_express/helpers/user.php');
				JLoader::register('RadicalMartHelperPlugins',
					JPATH_ADMINISTRATOR . '/components/com_radicalmart_express/helpers/plugins.php');
				$userHelper    = 'RadicalMartHelperUser';
				$pluginsHelper = 'RadicalMartHelperPlugins';
			}

			if (!$find)
			{
				throw new Exception(Text::_('PLG_AUTHENTICATION_RADICALMART_ERROR_COMPONENT_NOT_FOUND'));
			}

			// Prepare data
			$data = array(
				'username' => $credentials['username'],
				'email'    => $credentials['username'],
				'phone'    => $userHelper::cleanPhone($credentials['username']),
			);

			if (!$user = $userHelper::findUser($data))
			{
				throw new Exception(Text::_('JGLOBAL_AUTH_NO_USER'));
			}
			$credentials['username'] = $user->username;

			// Get authentication type
			$type = (!empty($credentials['type'])) ? $credentials['type'] : 'password';
			if ($type === 'rma_code')
			{
				// Authentication by code
				$code = (!empty($credentials['rma_code'])) ? trim($credentials['rma_code']) : '';
				if (empty($code))
				{
					throw new Exception(Text::_('PLG_AUTHENTICATION_RADICALMART_ERROR_EMPTY_CODE'));
				}

				$session = trim(Factory::getApplication()->getUserState('rma_code', ''));
				if (empty($session))
				{
					throw new Exception(Text::_('PLG_AUTHENTICATION_RADICALMART_ERROR_EMPTY_SESSION'));
				}

				if ($session !== $code)
				{
					throw new Exception(Text::_('PLG_AUTHENTICATION_RADICALMART_ERROR_CODE_INVALID'));
				}

				// Set response data
				$response->email         = $user->email;
				$response->fullname      = $user->name;
				$response->language      = (Factory::getApplication()->isClient('administrator'))
					? $user->getParam('admin_language') : $user->getParam('language');
				$response->status        = Authentication::STATUS_SUCCESS;
				$response->error_message = '';
			}
			else
			{
				// Authentication by password
				if (empty($credentials['password']))
				{
					throw new Exception(Text::_('JGLOBAL_AUTH_EMPTY_PASS_NOT_ALLOWED'));
				}

				// Check password
				$pluginsHelper::triggerPlugin('authentication', 'joomla', 'onUserAuthenticate',
					[$credentials, $options, &$response]);

				if ($response->status == JAuthentication::STATUS_FAILURE)
				{
					throw new Exception($response->error_message);
				}
			}

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