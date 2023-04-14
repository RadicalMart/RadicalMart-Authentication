<?php
/*
 * @package     RadicalMart Authentication
 * @subpackage  plg_authentication_radicalmart
 * @version     __DEPLOY_VERSION__
 * @author      Delo Design - delo-design.ru
 * @copyright   Copyright (c) 2023 Delo Design. All rights reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link        https://delo-design.ru/
 */

namespace Joomla\Plugin\Authentication\RadicalMart\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Authentication\Authentication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\User;
use Joomla\Component\RadicalMart\Administrator\Helper\UserHelper as RadicalMartUserHelper;
use Joomla\Component\RadicalMartExpress\Administrator\Helper\UserHelper as RadicalMartExpressUserHelper;
use Joomla\Event\SubscriberInterface;

class RadicalMart extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Load the language file on instantiation.
	 *
	 * @var    bool
	 *
	 * @since  1.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Loads the application object.
	 *
	 * @var  \Joomla\CMS\Application\CMSApplication
	 *
	 * @since  1.0.0
	 */
	protected $app = null;

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 *
	 * @since   2.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onUserAuthenticate' => 'onUserAuthenticate'
		];
	}

	/**
	 * This method should handle any authentication and report back to the subject.
	 *
	 * @param   array   $credentials  Array holding the user credentials.
	 * @param   array   $options      Array of extra options.
	 * @param   object  $response     Authentication response object.
	 *
	 * @since  1.0.0
	 */
	public function onUserAuthenticate(array &$credentials, array $options, object &$response)
	{
		// Check username
		$username = (!empty($credentials['username'])) ? (string) $credentials['username'] : false;
		if (!$username)
		{
			$response->status        = Authentication::STATUS_FAILURE;
			$response->error_message = Text::_('JGLOBAL_AUTH_NO_USER');

			return;
		}

		// Find user
		foreach (['getRadicalMartUser', 'getRadicalMartExpressUser'] as $method)
		{
			$user = $this->$method($username);
			if ($user)
			{
				break;
			}
		}

		if (!$user)
		{
			$response->status        = Authentication::STATUS_FAILURE;
			$response->error_message = Text::_('JGLOBAL_AUTH_NO_USER');

			return;
		}
		$credentials['username'] = $user->username;

		// Authenticate user
		$methods = [
			'password' => 'authenticateWithPassword',
			'rma_code' => 'authenticateWithCode',
		];
		$type    = (!empty($credentials['type'])) ? $credentials['type'] : 'password';
		$method  = (isset($methods[$type])) ? $methods[$type] : false;
		if ($method)
		{
			$this->$method($credentials, $options, $user, $response);
		}
	}

	/**
	 * Method to find user in RadicalMart.
	 *
	 * @param   string  $username  Authentication username.
	 *
	 * @throws \Exception
	 *
	 * @return false|User User object if found, False if not.
	 *
	 * @since 2.0.0
	 */
	protected function getRadicalMartUser(string $username)
	{
		return (!ComponentHelper::isEnabled('com_radicalmart')) ? false
			: RadicalMartUserHelper::findUser([
				'username' => $username,
				'email'    => $username,
				'phone'    => RadicalMartUserHelper::cleanPhone($username),
			]);
	}

	/**
	 * Method to find user in RadicalMart Express.
	 *
	 * @param   string  $username  Authentication username.
	 *
	 * @throws \Exception
	 *
	 * @return false|User User object on if find, False on not.
	 *
	 * @since 2.0.0
	 */
	protected function getRadicalMartExpressUser(string $username)
	{
		return (!ComponentHelper::isEnabled('com_radicalmart_express')) ? false
			: RadicalMartExpressUserHelper::findUser([
				'username' => $username,
				'email'    => $username,
				'phone'    => RadicalMartExpressUserHelper::cleanPhone($username),
			]);
	}

	/**
	 * Method for authenticate user with password.
	 *
	 * @param   array   $credentials  Array holding the user credentials.
	 * @param   array   $options      Array of extra options.
	 * @param   User    $user         Find user object.
	 * @param   object  $response     Authentication response object.
	 *
	 * @since  2.0.0
	 */
	protected function authenticateWithPassword(array $credentials, array $options, User $user, object &$response)
	{
		// Authentication by password
		if (empty($credentials['password']))
		{
			$response->status        = Authentication::STATUS_FAILURE;
			$response->error_message = Text::_('JGLOBAL_AUTH_EMPTY_PASS_NOT_ALLOWED');

			return;
		}

		// Check password
		/* @var \PlgAuthenticationJoomla $plugin */
		$plugin = $this->app->bootPlugin('joomla', 'authentication');
		$plugin->onUserAuthenticate($credentials, $options, $response);
	}

	/**
	 * Method for authenticate user with code.
	 *
	 * @param   array   $credentials  Array holding the user credentials.
	 * @param   array   $options      Array of extra options.
	 * @param   User    $user         Find user object.
	 * @param   object  $response     Authentication response object.
	 *
	 * @since  2.0.0
	 */
	protected function authenticateWithCode(array $credentials, array $options, User $user, object &$response)
	{
		try
		{
			// Get credentials code
			$code = (!empty($credentials['rma_code'])) ? trim((string) $credentials['rma_code']) : '';
			if (empty($code))
			{
				throw new \Exception(Text::_('PLG_AUTHENTICATION_RADICALMART_ERROR_EMPTY_CODE'));
			}

			// Get session code
			$session = trim((string) $this->app->getUserState('rma_code', ''));
			if (empty($session))
			{
				throw new \Exception(Text::_('PLG_AUTHENTICATION_RADICALMART_ERROR_EMPTY_SESSION'));
			}

			// Compare  credentials && session codes.
			if ($session !== $code)
			{
				throw new \Exception(Text::_('PLG_AUTHENTICATION_RADICALMART_ERROR_CODE_INVALID'));
			}

			// Set response data
			$response->email         = $user->email;
			$response->fullname      = $user->name;
			$response->language      = ($this->app->isClient('administrator'))
				? $user->getParam('admin_language') : $user->getParam('language');
			$response->status        = Authentication::STATUS_SUCCESS;
			$response->error_message = '';
		}
		catch (\Exception $e)
		{
			$response->status        = Authentication::STATUS_FAILURE;
			$response->error_message = $e->getMessage();
		}
	}
}