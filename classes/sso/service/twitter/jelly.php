<?php defined('SYSPATH') or die('No direct access allowed.');

class SSO_Service_Twitter_Jelly extends SSO_Service_Twitter {

	/**
	 * Complete the login
	 *
	 * @return  boolean
	 */
	protected function complete_login()
	{
		if ($this->oauth_token AND $this->oauth_token->token !== Arr::get($_GET, 'oauth_token'))
		{
			// Delete the token, it is not valid
			Session::instance()->delete($this->oauth_cookie);

			// Send the user back to the beginning
			Request::current()->redirect(URL::site($this->sso_config['login'], Request::current()));
		}

		// Get the verifier
		$verifier = Arr::get($_GET, 'oauth_verifier');

		// Store the verifier in the token
		$this->oauth_token->verifier($verifier);

		// Exchange the request token for an access token
		$this->oauth_token = $this->oauth_provider->access_token($this->oauth_consumer, $this->oauth_token);

		try
		{
			// Get user details
			$data = Twitter::factory('account')->verify_credentials($this->oauth_consumer, $this->oauth_token);
		}
		catch (Kohana_OAuth_Exception $e)
		{
			// Log the error and return false
			Kohana::$log->add(Log::ERROR, Kohana_Exception::text($e));
		    return FALSE;
		}

		// Set provider field
		$provider_field = $this->sso_service.'_id';

		// Check whether that id exists in our users table (provider id field)
		$user = Jelly::query('user_sso_jelly')->where($provider_field, '=', $data->id)->limit(1)->select();

		// Data to array
		$data = (array) $data;

		// Signup if necessary
		Jelly::query('user_sso_jelly')->signup_sso($user, $data, $provider_field);

		// Give the user a normal login session
		Auth::instance()->force_login_sso($user->$provider_field, $this->sso_service);

		return TRUE;
	}

} // End SSO_Service_Twitter_Jelly