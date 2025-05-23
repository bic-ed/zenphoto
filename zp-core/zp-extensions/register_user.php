<?php
/**
 * Support for allowing visitors to register to access your site. Users registering
 * are verified via an e-mail to insure the validity of the e-mail address they provide.
 * Options are provided for setting the required registration details and the default
 * user rights that will be granted.
 *
 * Place a call on <i>registerUser::printForm()</i> where you want the form to appear.
 * Probably the best use is to create a new <i>custom page</i> script just for handling these
 * user registrations. Then put a link to that script on your index page so that people
 * who wish to register will click on the link and be taken to the registration page.
 *
 * When successfully registered, a new Zenphoto user will be created with no logon rights. An e-mail
 * will be sent to the user with a link to activate the user ID. When he clicks on that link
 * he will be taken to the registration page and the verification process will be completed.
 * At this point the user ID rights are set to the value of the plugin default user rights option
 * and an email is sent to the Gallery admin announcing the new registration.
 *
 * <b>NOTE:</b> If you change the rights of a user pending verification you have verified the user!
 *
 * @author Stephen Billard (sbillard)
 * @package zpcore\plugins\registeruser
 */
$plugin_is_filter = 5 | FEATURE_PLUGIN;
$plugin_description = gettext("Provides a means for placing a user registration form on your theme pages.");
$plugin_author = "Stephen Billard (sbillard)";
$plugin_category = gettext('Users');

$option_interface = 'registerUserOptions';

$_zp_conf_vars['special_pages']['register_user'] = array(
		'define' => '_REGISTER_USER_',
		'rewrite' => getOption('register_user_link'),
		'option' => 'register_user_link',
		'default' => '_PAGE_/register');
$_zp_conf_vars['special_pages'][] = array(
		'definition' => '%REGISTER_USER%',
		'rewrite' => '_REGISTER_USER_');

$_zp_conf_vars['special_pages'][] = array(
		'define' => false,
		'rewrite' => '%REGISTER_USER%',
		'rule' => '^%REWRITE%/*$		index.php?p=' .
		'register' . ' [L,QSA]');

if (getOption('register_user_address_info')) {
	require_once(SERVERPATH . '/' . ZENFOLDER . '/' . PLUGIN_FOLDER . '/comment_form/functions.php');
}

/**
 * Options class
 */
class registerUserOptions {
	
	public static $common_notify_handler = '';

	function __construct() {
		global $_zp_authority;
		purgeOption('register_user_page_tip'); // unused anyway
		
		setOptionDefault('register_user_link', '_PAGE_/register');
		setOptionDefault('register_user_text', '');
		setOptionDefault('register_user_text_auth', '');
		setOptionDefault('register_user_page_link', 1);
		setOptionDefault('register_user_page_linktext', '');
		setOptionDefault('register_user_captcha', 0);
		setOptionDefault('register_user_email_is_id', 1);
		setOptionDefault('register_user_create_album', 0);
		$mailinglist = $_zp_authority->getAdminEmail(ADMIN_RIGHTS);
		if (count($mailinglist) == 0) { //	no one to send the notice to!
			setOption('register_user_notify', 0);
		} else {
			setOptionDefault('register_user_notify', 1);
		}
		setOptionDefault('register_user_moderated', 0);
		setOptionDefault('register_user_dataconfirmation', 0);
		setOptionDefault('register_user_textquiz', 0);
		setOptionDefault('register_user_textquiz_question', '');
		setOptionDefault('register_user_textquiz_answer', '');
		setOptionDefault('register_user_mathquiz', 0);
		setOptionDefault('register_user_mathquiz_question', '');
		
	}

	function getOptionsSupported() {
		global $_zp_authority, $_zp_captcha;
		$options = array(
				gettext('Link text') => array(
						'key' => 'register_user_page_linktext',
						'type' => OPTION_TYPE_TEXTAREA,
						'desc' => gettext('The link text to the user register page on the login form. Leave empty to use the default.')),
				gettext('Link on login form') => array(
						'key' => 'register_user_page_link',
						'type' => OPTION_TYPE_CHECKBOX,
						'desc' => gettext('If this option is set, the visitor login form will include a link to this page. The link text will be labeled with the text provided above.')),
				gettext('Notify*') => array(
						'key' => 'register_user_notify',
						'type' => OPTION_TYPE_CHECKBOX,
						'desc' => gettext('If checked, an e-mail will be sent to the gallery admins when a new user has verified his registration.')),
				gettext('Moderated registrations') => array(
						'key' => 'register_user_moderated',
						'type' => OPTION_TYPE_CHECKBOX,
						'desc' => gettext('If checked, registrants can be reviewed first and do not get an automatic verfification mail. You can either approve users manually or send the verification request manually after reviewing the user. The latter is recommended in some jurisdictions like the EU.')),
				gettext('User album') => array(
						'key' => 'register_user_create_album',
						'type' => OPTION_TYPE_CHECKBOX,
						'desc' => gettext('If checked, an album will be created and assigned to the user.')),
				gettext('Email ID') => array(
						'key' => 'register_user_email_is_id',
						'type' => OPTION_TYPE_CHECKBOX,
						'desc' => gettext('If checked, the user’s e-mail address will be used as his User ID.')),
				gettext('Email notification text (Verification request)') => array(
						'key' => 'register_user_text',
						'type' => OPTION_TYPE_TEXTAREA,
						'desc' => gettext('Text for the body of the email sent to the registrant for registration verification. Leave empty to use the default text. <p class="notebox"><strong>Note:</strong> You must include <code>%1$s</code> in your message where you wish the <em>registration verification</em> link to appear. You may also insert the registrant’s <em>name</em> (<code>%2$s</code>) and <em>user id</em> (<code>%3$s</code>).</p>')),
				gettext('Email notification text (Authentication)') => array(
						'key' => 'register_user_text_auth',
						'type' => OPTION_TYPE_TEXTAREA,
						'desc' => gettext('Text for the body of the email sent to the registrant if authenticated manually by an admin. Leave empty to use the default text.')),
				gettext('Data usage confirmation') => array(
						'key' => 'register_user_dataconfirmation',
						'type' => OPTION_TYPE_CHECKBOX,
						'desc' => gettext('If checked a mandatory checkbox is added for users to agree with data storage and handling by your site. This is recommend to comply with the European GDPR.')),
				gettext('CAPTCHA') => array(
						'key' => 'register_user_captcha',
						'type' => OPTION_TYPE_CHECKBOX,
						'desc' => ($_zp_captcha->name) ? gettext('If checked, the form will include a Captcha verification.') : '<span class="notebox">' . gettext('No captcha handler is enabled.') . '</span>'),
				gettext('Math quiz') => array(
						'key' => 'register_user_mathquiz',
						'type' => OPTION_TYPE_CHECKBOX,
						'desc' => gettext('Enables a mandatory input field so users have to answer a math question before they can send any mail.')),
				gettext('Math quiz question') => array(
						'key' => 'register_user_mathquiz_question',
						'type' => OPTION_TYPE_TEXTBOX,
						'desc' => gettext('The question for the math quiz. Enter a valid PHP expression like 2+3*2. Allowed chars: <code>0-9+-*/.()</code>.')),
				gettext('Text quiz') => array(
						'key' => 'register_user_textquiz',
						'type' => OPTION_TYPE_CHECKBOX,
						'desc' => gettext('Enables a mandatory input field so users have to answer a text question before they can send any mail.')),
				gettext('Text quiz question') => array(
						'key' => 'register_user_textquiz_question',
						'type' => OPTION_TYPE_TEXTBOX,
						'multilingual' => 1,
						'desc' => gettext('The question for the text quiz.')),
				gettext('Text quiz answer') => array(
						'key' => 'register_user_textquiz_answer',
						'type' => OPTION_TYPE_TEXTBOX,
						'multilingual' => 1,
						'desc' => gettext('The answer to the text quiz question.'))
		);
		if (extensionEnabled('userAddressFields')) {
			$options[gettext('Address fields')] = array(
					'key' => 'register_user_address_info',
					'type' => OPTION_TYPE_RADIO,
					'buttons' => array(gettext('Omit') => 0, gettext('Show') => 1, gettext('Require') => 'required'),
					'desc' => gettext('If <em>Address fields</em> are shown or required, the form will include positions for address information. If required, the user must supply data in each address field.'));
		}
		if (registerUserOptions::$common_notify_handler) {
			$options['note'] = array(
					'key' => 'menu_truncate_note',
					'type' => OPTION_TYPE_NOTE,
					'desc' => '<p class="notebox">' . registerUserOptions::$common_notify_handler . '</p>');
		} else {
			registerUserOptions::$common_notify_handler = gettext('* The option may be set via the <a href="javascript:gotoName(\'register_user\');"><em>register_user</em></a> plugin options.');
			$options['note'] = array(
					'key' => 'menu_truncate_note',
					'type' => OPTION_TYPE_NOTE,
					'desc' => gettext('<p class="notebox">*<strong>Note:</strong> The setting of this option is shared with other plugins.</p>'));
		}
		$mailinglist = $_zp_authority->getAdminEmail(ADMIN_RIGHTS);
		if (count($mailinglist) == 0) { //	no one to send the notice to!
			$options[gettext('Notify*')]['disabled'] = true;
			$options[gettext('Notify*')]['desc'] .= ' ' . gettext('Of course there must be some Administrator with an e-mail address for this option to make sense!');
		}
		
		if (class_exists('user_groups')) {
			$admins = $_zp_authority->getAdministrators('groups');
			$defaultrights = ALL_RIGHTS;
			$ordered = array();
			foreach ($admins as $key => $admin) {
				$ordered[$admin['user']] = $admin['user'];
				if ($admin['rights'] < $defaultrights && $admin['rights'] >= NO_RIGHTS) {
					$nullselection = $admin['user'];
					$defaultrights = $admin['rights'];
				}
			}
			if (!empty($nullselection)) {
				if (is_numeric(getOption('register_user_user_rights'))) {
					setOption('register_user_user_rights', $nullselection);
				} else {
					setOptionDefault('register_user_user_rights', $nullselection);
				}
			}
			$options[gettext('Default user group')] = array(
					'key' => 'register_user_user_rights',
					'type' => OPTION_TYPE_SELECTOR,
					'selections' => $ordered,
					'desc' => gettext("Initial group assignment for the new user."));
		} else {
			if (is_numeric(getOption('register_user_user_rights'))) {
				setOptionDefault('register_user_user_rights', USER_RIGHTS);
			} else {
				setOption('register_user_user_rights', USER_RIGHTS);
			}
			$options[gettext('Default rights')] = array(
					'key' => 'register_user_user_rights',
					'type' => OPTION_TYPE_CUSTOM,
					'desc' => gettext("Initial rights for the new user. (If no rights are set, approval of the user will be required.)"));
		}
		return $options;
	}

	function handleOption($option, $currentValue) {
		switch ($option) {
			case 'register_user_user_rights':
				printAdminRightsTable('register_user', '', '', getOption('register_user_user_rights'));
				break;
		}
	}

	function handleOptionSave($themename, $themealbum) {
		if (!class_exists('user_groups')) {
			$saved_rights = NO_RIGHTS;
			$rightslist = sortMultiArray(Authority::getRights(), array('set', 'value'));
			foreach ($rightslist as $rightselement => $right) {
				if (isset($_POST['register_user-' . $rightselement])) {
					$saved_rights = $saved_rights | $_POST['register_user-' . $rightselement];
				}
			}
			setOption('register_user_user_rights', $saved_rights);
		}
		return false;
	}
}

/**
 * Plugin class
 * @since 1.6.5 - Renamed from register_user to registerUser
 */
class registerUser {

	public static $user = '';
	public static $admin_name = '';
	public static $admin_email = '';
	public static $notify = '';
	public static $link = '';
	public static $message = '';

	/**
	 * Processes the post of an address
	 *
	 * @param int $i sequence number of the comment
	 * @return array
	 */
	static function getUserInfo($i) {
		$result = array();
		if (isset($_POST[$i . '-comment_form_website'])) {
			$result['website'] = sanitize($_POST[$i . '-comment_form_website'], 1);
		}
		if (isset($_POST[$i . '-comment_form_street'])) {
			$result['street'] = sanitize($_POST[$i . '-comment_form_street'], 1);
		}
		if (isset($_POST[$i . '-comment_form_city'])) {
			$result['city'] = sanitize($_POST[$i . '-comment_form_city'], 1);
		}
		if (isset($_POST[$i . '-comment_form_state'])) {
			$result['state'] = sanitize($_POST[$i . '-comment_form_state'], 1);
		}
		if (isset($_POST[$i . '-comment_form_country'])) {
			$result['country'] = sanitize($_POST[$i . '-comment_form_country'], 1);
		}
		if (isset($_POST[$i . '-comment_form_postal'])) {
			$result['postal'] = sanitize($_POST[$i . '-comment_form_postal'], 1);
		}
		return $result;
	}

	/**
	 * Gets the register user link
	 * @return string
	 */
	static function getLink() {
		return zp_apply_filter('getLink', rewrite_path(_REGISTER_USER_ . '/', '/index.php?p=register'), 'register.php', NULL);
	}

	/**
	 * Processes the user registration submission
	 * 
	 * @global obj $_zp_authority
	 * @global obj $_zp_captcha
	 * @global obj $_zp_gallery
	 */
	static function postProcessor() {
		global $_zp_authority, $_zp_captcha;
		//Handle registration
		if (isset($_POST['username']) && !empty($_POST['username'])) {
			registerUser::$notify = 'honeypot'; // honey pot check
		}
		if (getOption('register_user_captcha')) {
			if (isset($_POST['code'])) {
				$code = sanitize($_POST['code'], 3);
				$code_ok = sanitize($_POST['code_h'], 3);
			} else {
				$code = '';
				$code_ok = '';
			}
			if (!$_zp_captcha->checkCaptcha($code, $code_ok)) {
				registerUser::$notify = 'invalidcaptcha';
			}
		}
		registerUser::$admin_name = trim(strval(sanitize($_POST['admin_name'])));
		if (empty(registerUser::$admin_name)) {
			registerUser::$notify = 'incomplete';
		}
		registerUser::$user = trim(strval(sanitize($_POST['user'])));
		if (getOption('register_user_email_is_id')) {
			$mail_duplicate = $_zp_authority->isUniqueMailaddress(registerUser::$user, registerUser::$user);
			if (!$mail_duplicate) {
				registerUser::$notify = 'exists';
			}
		}
		if (isset($_POST['admin_email'])) {
			registerUser::$admin_email = trim(strval(sanitize($_POST['admin_email'])));
			$mail_duplicate = $_zp_authority->isUniqueMailaddress(registerUser::$admin_email, registerUser::$user);
			if (!$mail_duplicate) {
				registerUser::$notify = 'duplicateemail';
			}
		} else {
			registerUser::$admin_email = registerUser::$user;
		}
		if (!isValidEmail(registerUser::$admin_email)) {
			registerUser::$notify = 'invalidemail';
		}
		if (getOption('register_user_dataconfirmation') && !isset($_POST['admin_dataconfirmation'])) {
			registerUser::$notify = 'dataconfirmationmissing';
		}
		
		if (registerUser::getQuizFieldQuestion('register_user_textquiz')) {
			$textquiz_error = false;
			if (isset($_POST['admin_textquiz'])) {
				$textquiz_answer = strtolower(trim(strval(get_language_string(getOption('register_user_textquiz_answer')))));
				$textquiz_answer_user = strtolower(trim(strval(sanitize($_POST['admin_textquiz']))));
				if (empty($textquiz_answer_user) || $textquiz_answer_user != $textquiz_answer) {
					$textquiz_error = true;
				}
			} else {
				$textquiz_error = true;
			}
			if ($textquiz_error) {
				registerUser::$notify = 'invalidtextquiz';
			}
		}
		if (registerUser::getQuizFieldQuestion('register_user_mathquiz')) {
			$mathquiz_error = false;
			if (isset($_POST['admin_mathquiz'])) {
				$mathquiz_answer = eval('return ' . registerUser::getQuizFieldQuestion('register_user_mathquiz') . ';');
				$mathquiz_answer_user = trim(sanitize($_POST['admin_mathquiz']));
				if (empty($mathquiz_answer_user) || $mathquiz_answer_user != $mathquiz_answer) {
					$mathquiz_error = true;
				}
			} else {
				$mathquiz_error = true;
			}
			if ($mathquiz_error) {
				registerUser::$notify = 'invalidmathquiz';
			}
		}


		$pass = trim(sanitize($_POST['pass']));
		if (empty($pass)) {
			registerUser::$notify = 'empty';
		} else if (!empty(registerUser::$user) && !(empty(registerUser::$admin_name)) && !empty(registerUser::$admin_email)) {
			if (isset($_POST['disclose_password']) || $pass == trim(sanitize($_POST['pass_r']))) {
				$currentadmin = Authority::getAnAdmin(array('`user`=' => registerUser::$user, '`valid`>' => 0));
				if (is_object($currentadmin)) {
					registerUser::$notify = 'exists';
				}
				if (empty(registerUser::$notify)) {
					$userobj = Authority::newAdministrator('');
					$userobj->transient = false;
					$userobj->setUser(registerUser::$user);
					$userobj->setPass($pass);
					$userobj->setName(registerUser::$admin_name);
					$userobj->setEmail(registerUser::$admin_email);
					$userobj->setRights(0);
					$userobj->setObjects(NULL);
					$userobj->setGroup('');
					$userobj->setCustomData('');
					$userobj->setLanguage(getUserLocale());
					if (extensionEnabled('userAddressFields')) {
						$addresses = getOption('register_user_address_info');
						$userinfo = registerUser::getUserInfo(0);
						$comment_form_save_post = serialize($userinfo);
						if ($addresses == 'required') {
							if (!isset($userinfo['street']) || empty($userinfo['street'])) {
								$userobj->transient = true;
								$userobj->msg .= ' ' . gettext('You must supply the street field.');
							}
							if (!isset($userinfo['city']) || empty($userinfo['city'])) {
								$userobj->transient = true;
								$userobj->msg .= ' ' . gettext('You must supply the city field.');
							}
							if (!isset($userinfo['state']) || empty($userinfo['state'])) {
								$userobj->transient = true;
								$userobj->msg .= ' ' . gettext('You must supply the state field.');
							}
							if (!isset($userinfo['country']) || empty($userinfo['country'])) {
								$userobj->transient = true;
								$userobj->msg .= ' ' . gettext('You must supply the country field.');
							}
							if (!isset($userinfo['postal']) || empty($userinfo['postal'])) {
								$userobj->transient = true;
								$userobj->msg .= ' ' . gettext('You must supply the postal code field.');
							}
						}
						zp_setCookie('reister_user_form_addresses', $comment_form_save_post);
						userAddressFields::setCustomData($userobj, $userinfo);
					}

					zp_apply_filter('register_user_registered', $userobj);
					if ($userobj->transient) {
						if (empty(registerUser::$notify)) {
							registerUser::$notify = 'filter';
						}
					} else {
						$userobj->save();
						$subject = sprintf(gettext('New user registration on your site %s'), getGalleryTitle());
						if (getOption('register_user_moderated')) {
							registerUser::$notify = 'accepted';
							$message = sprintf(gettext('%1$s (%2$s) has registered for your site providing an e-mail address of %3$s and requires your moderation.'), $userobj->getName(), $userobj->getUser(), $userobj->getEmail());
						} else {
							registerUser::$notify = registerUser::sendVerificationEmail($userobj);
							if (empty(registerUser::$notify)) {
								registerUser::$notify = 'accepted';
								$message = sprintf(gettext('%1$s (%2$s) has registered for your site providing an e-mail address of %3$s and has been sent a verification request email.'), $userobj->getName(), $userobj->getUser(), $userobj->getEmail());
							}
						}
						if (getOption('register_user_notify')) {
							$_zp_authority->sendAdminNotificationEmail($subject, $message, 'alladmins');
						} 
					}
				}
			} else {
				registerUser::$notify = 'mismatch';
			}
		} else {
			registerUser::$notify = 'incomplete';
		}
	}
	
	/**
	 * Sends a verification email to a user. The mode has impact on the email message send.
	 * 
	 * @since 1.6.6
	 * 
	 * @param obj $userobj
	 * @param string $mode 'verification' self-verification via email, 'authentication' for manual aunthentication by an admin
	 * @return string
	 */
	static function sendVerificationEmail($userobj, $mode = 'verification') {
		if (MOD_REWRITE) {
			$verify = '?verify=';
		} else {
			$verify = '&verify=';
		}
		$link = SERVER_HTTP_HOST . registerUser::getLink() . $verify . bin2hex(serialize(array('user' => $userobj->getUser(), 'email' => $userobj->getEmail())));
		switch ($mode) {
			default:
			case 'verification':
				$subject = sprintf(gettext('Registration confirmation required for the site %1$s (%2$s)'), getGalleryTitle(), FULLWEBPATH);
				$message = get_language_string(getOption('register_user_text'));
				if (!$message) {
					$message = gettext('You have received this email because you registered with the user id %3$s on this site.' . "\n" . 'To complete your registration visit %1$s');
				}
				break;
			case 'authentication':
				$subject = sprintf(gettext('Registration authenticated for the site %1$s (%2$s)'), getGalleryTitle(), FULLWEBPATH);
				$message = get_language_string(getOption('register_user_text_auth'));
				if (!$message) {
					$message = gettext('You have received this email because you registered with the user id %3$s on this site.' . "\n" . 'Your registration has been authenticated by an administrator.');
				}
				break;
		}
		$message_final = sprintf($message, $link, $userobj->getName(), $userobj->getUser());
		return zp_mail($subject,$message_final, array($userobj->getUser() => $userobj->getEmail()));
	}

	/**
	 * Parses the verification and registration if they have occurred
	 * places the user registration form
	 * 
	 * @since 1.6.5 – Moved to registerUser class and renamed to printForm()
	 *
	 * @param string $thanks the message shown on successful registration
	 */
	static function printForm($thanks = null) {
		global $_zp_authority, $_zp_captcha;
		require_once(SERVERPATH . '/' . ZENFOLDER . '/admin-functions.php');
		$userobj = NULL;
		// handle any postings
		if (isset($_GET['verify'])) {
			$currentadmins = $_zp_authority->getAdministrators();
			$params = sanitize(unserialize(pack("H*", trim($_GET['verify'])), ['allowed_classes' => false]));
			// expung the verify query string as it will cause us to come back here if login fails.
			unset($_GET['verify']);
			registerUser::$link = explode('?', getRequestURI());
			$p = array();
			if (isset(registerUser::$link[1])) {
				$p = explode('&', registerUser::$link[1]);
				foreach ($p as $k => $v) {
					if (strpos($v, 'verify=') === 0) {
						unset($p[$k]);
					}
				}
				unset($p['verify']);
			}
			$_SERVER['REQUEST_URI'] = registerUser::$link[0];
			if (!empty($p)) {
				$_SERVER['REQUEST_URI'] .= '?' . implode('&', $p);
			}

			$userobj = Authority::getAnAdmin(array('`user`=' => $params['user'], '`valid`=' => 1));
			if ($userobj && $userobj->getEmail() == $params['email']) {
				if (!$userobj->getRights()) {
					$userobj->setCredentials(array('registered', 'user', 'email'));
					$rights = getOption('register_user_user_rights');
					$group = NULL;
					if (!is_numeric($rights)) { //  a group or template
						$admin = Authority::getAnAdmin(array('`user`=' => $rights, '`valid`=' => 0));
						if ($admin) {
							$userobj->setObjects($admin->getObjects());
							if ($admin->getName() != 'template') {
								$group = $rights;
							}
							$rights = $admin->getRights();
						} else {
							$rights = USER_RIGHTS; //NO_RIGHTS;
						}
					}
					$userobj->setRights($rights | NO_RIGHTS); 
					$userobj->setGroup($group);
					zp_apply_filter('register_user_verified', $userobj);
					if (getOption('register_user_notify')) {
						$subject = sprintf(gettext('New user verification on your site %s'), getGalleryTitle());
						$message = sprintf(gettext('%1$s (%2$s) has registered and verified for your site providing an e-mail address of %3$s.'), $userobj->getName(), $userobj->getUser(), $userobj->getEmail());
						registerUser::$notify = $_zp_authority->sendAdminNotificationEmail($subject, $message, 'alladmins');
					}
					if (empty(registerUser::$notify)) {
						if (getOption('register_user_create_album')) {
							$userobj->createPrimealbum();
						}
						registerUser::$notify = 'verified';
						$_POST['user'] = $userobj->getUser();
					}
					$userobj->save();
				} else {
					registerUser::$notify = 'already_verified';
				}
			} else {
				registerUser::$notify = 'not_verified'; // User ID no longer exists
			}
		}

		if (zp_loggedin()) {
			if (isset($_GET['login'])) {
				echo '<meta http-equiv="refresh" content="1; url=' . WEBPATH . '/">';
			} else {
				echo '<div class="errorbox fade-message">';
				echo '<h2>' . gettext("you are already logged in.") . '</h2>';
				echo '</div>';
			}
			return;
		}
		if (isset($_GET['login'])) { //presumably the user failed to login....
			registerUser::$notify = 'loginfailed';
		}
		if (!empty(registerUser::$notify)) {
			switch (registerUser::$notify) {
				case'verified':
					if (is_null($thanks)) {
						$thanks = gettext("Thank you for registering.");
					}
					?>
					<div class="messagebox fade-message">
						<p><?php echo $thanks; ?></p>
						<p><?php echo gettext('You may now log onto the site and verify your personal information.'); ?></p>
					</div>
				<?php
				case 'already_verified':
				case 'loginfailed':
					registerUser::$link = getRequestURI();
					if (strpos(registerUser::$link, '?') === false) {
						$_SERVER['REQUEST_URI'] = registerUser::$link . '?login=true';
					} else {
						$_SERVER['REQUEST_URI'] = registerUser::$link . '&login=true';
					}
					require_once(SERVERPATH . '/' . ZENFOLDER . '/' . PLUGIN_FOLDER . '/user_login-out.php');
					printPasswordForm(NULL, true, false, WEBPATH . '/' . ZENFOLDER . '/admin-users.php?page=users');
					registerUser::$notify = 'success';
					break;
				case 'honeypot': //pretend it was accepted
				case 'accepted':
					?>
					<div class="messagebox fade-message">
						<p>
						<?php 
						if (getOption('register_user_moderated')) {
							echo gettext('Your registration information has been received. Please note that registrations are moderated. If your registration has been approved you will be sent an email to verify your email address.');
						} else {
							echo gettext('Your registration information has been accepted. An email has been sent to you to verify your email address.'); 
						}
						?>
						</p>
					</div>
					<?php
					if (registerUser::$notify != 'honeypot') {
						registerUser::$notify = 'success'; // of course honeypot catches are no success!
					}
					break;
				case 'exists':
					?>
					<div class="errorbox fade-message">
						<h2><?php echo gettext("Registration failed."); ?></h2>
					<?php
					if (getOption('register_user_email_is_id')) {
						$idnote = registerUser::$admin_email;
					} else {
						$idnote = registerUser::$user;
					}
					?>
						<p><?php printf(gettext('The user ID <em>%s</em> is already in use.'), $idnote); ?></p>
					</div>
						<?php
						break;
					case 'empty':
						?>
					<div class="errorbox fade-message">
						<h2><?php echo gettext("Registration failed."); ?></h2>
						<p><?php echo gettext('Passwords may not be empty.'); ?></p>
					</div>
					<?php
					break;
				case 'mismatch':
					?>
					<div class="errorbox fade-message">
						<h2><?php echo gettext("Registration failed."); ?></h2>
						<p><?php echo gettext('Your passwords did not match.'); ?></p>
					</div>
					<?php
					break;
				case 'incomplete':
					?>
					<div class="errorbox fade-message">
						<h2><?php echo gettext("Registration failed."); ?></h2>
						<p><?php echo gettext('You have not filled in all the fields.'); ?></p>
					</div>
					<?php
					break;
				case 'invalidemail':
					?>
					<div class="errorbox fade-message">
						<h2><?php echo gettext("Registration failed."); ?></h2>
						<p><?php echo gettext('Enter a valid email address.'); ?></p>
					</div>
					<?php
					break;
				case 'duplicateemail':
					?>
					<div class="errorbox fade-message">
						<h2><?php echo gettext("Registration failed."); ?></h2>
						<p><?php echo gettext('The email address entered is already used.'); ?></p>
					</div>
					<?php
					break;
				case 'invalidcaptcha':
					?>
					<div class="errorbox fade-message">
						<h2><?php echo gettext("Registration failed."); ?></h2>
						<p><?php echo gettext('The CAPTCHA you entered was not correct.'); ?></p>
					</div>
					<?php
					break;
				case 'invalidmathquiz':
					?>
					<div class="errorbox fade-message">
						<h2><?php echo gettext("Registration failed."); ?></h2>
						<p><?php echo gettext('The math quiz answer you entered was not correct.'); ?></p>
					</div>
					<?php
					break;
				case 'invalidtextquiz':
					?>
					<div class="errorbox fade-message">
						<h2><?php echo gettext("Registration failed."); ?></h2>
						<p><?php echo gettext('The text quiz answer you entered was not correct.'); ?></p>
					</div>
					<?php
					break;
				case 'not_verified':
					?>
					<div class="errorbox fade-message">
						<h2><?php echo gettext("Verification failed."); ?></h2>
						<p><?php echo gettext('Your registration request could not be completed.'); ?></p>
					</div>
					<?php
					break;
				case 'filter':
					?>
					<div class="errorbox fade-message">
						<h2><?php echo gettext("Registration failed."); ?></h2>
						<p>
					<?php
					if (is_object($userobj) && !empty($userobj->msg)) {
						echo $userobj->msg;
					} else {
						echo gettext('Your registration attempt failed a <code>register_user_registered</code> filter check.');
					}
					?>
						</p>
					</div>
							<?php
							break;
						case 'dataconfirmationmissing':
							?>
					<div class="errorbox fade-message">
						<h2><?php echo gettext("Data usage confirmation missing."); ?></h2>
						<p><?php echo gettext('You have not agreed to data storage and handling by this website.'); ?></p>
					</div>
					<?php
					break;
				default:
					?>
					<div class="errorbox fade-message">
						<h2><?php echo gettext("Registration failed."); ?></h2>
						<p><?php echo registerUser::$notify; ?></p>
					</div>
					<?php
					break;
			}
		}
		if (registerUser::$notify != 'success') {
			$form = getPlugin('register_user/register_user_form.php', true);
			require_once($form);
			if (getOption('register_user_moderated')) {
				echo '<p><strong>' . gettext('Please note: Registrations are moderated.') . '</strong></p>';
			}
		}
		
	}

	/**
	 * prints the link to the register user page
	 * 
	 * @since 1.6.5 – Moved to registerUser class and renamed to printLink()
	 *
	 * @param string $linktext text for the link
	 * @param string $prev text to insert before the URL
	 * @param string $next text to follow the URL
	 * @param string $class optional class
	 */
	static function printLink($linktext, $prev = '', $next = '', $class = NULL) {
		if (!zp_loggedin()) {
			if (!is_null($class)) {
				$class = 'class="' . $class . '"';
			}
			if (is_null($linktext) && getOption('register_user_page_link')) {
				$linktext = get_language_string(getOption('register_user_page_linktext'));
				if (!$linktext) {
					$linktext = gettext('Register');
				}
			}
			echo $prev;
			?>
			<a href="<?php echo html_encode(registerUser::getLink()); ?>"<?php echo $class; ?> title="<?php echo html_encode($linktext); ?>" id="register_link"><?php echo $linktext; ?></a>
			<?php
			echo $next;
		}
	}
	
	/**
	 * Gets the the question to a quiz field if the field is enabled and setup correctly
	 * 
	 * @since 1.6.5 
	 * 
	 * @param string $which
	 * @return string|bool
	 */
	static function getQuizFieldQuestion($which = '') {
		if (!zp_loggedin()) {
			switch ($which) {
				case 'register_user_textquiz':
					if (getOption($which)) {
						$question = trim(get_language_string(getOption('register_user_textquiz_question')));
						$answer = trim(get_language_string(getOption('register_user_textquiz_question')));
						if (!empty($question) && !empty($answer)) {
							return $question;
						}
					}
					break;
				case 'register_user_mathquiz':
					if (getOption($which)) {
						$question = get_language_string(getOption('register_user_mathquiz_question'));
						// filter in case a user entered invalid expression
						$question_filtered = trim(preg_replace("/[^0-9\-\*\+\/\().]/", '', $question));
						if (!empty($question_filtered)) {
							return $question_filtered;
						}
					}
					break;
			}
		}
		return false;
	}
	
}

/**
 * Plugin class
 * 
 * @deprecated 2.0 - Use the class registerUser instead
 *
 */
class register_user {

	/**
	 * @deprecated 2.0 - Use registerUser::getUserInfo() instead
	 * @param type $i
	 * @return type
	 */
	static function getUserInfo($i) {
		deprecationNotice(gettext('Use registerUser::getUserInfo() instead'));
		return registerUser::getUserInfo($i);
	}

	/**
	 * @deprecated 2.0 Use registerUser::getLink() instead
	 * @return type
	 */
	static function getLink() {
		deprecationNotice(gettext('Use registerUser::getLink() instead'));
		return registerUser::getLink();
	}

	/**
	 * @deprecated 2.0 Use registerUser::postProcessor() instead
	 */
	static function post_processor() {
		deprecationNotice(gettext('Use registerUser::postProcessor() instead'));
		registerUser::postProcessor();
	}
}

/**
 * Parses the verification and registration if they have occurred
 * places the user registration form
 * 
 * @deprecated 2.0 – Use registerUser::printForm() instead
 *
 * @param string $thanks the message shown on successful registration
 */
function printRegistrationForm($thanks = NULL) {
	deprecationNotice(gettext('Use registerUser::printForm() instead'));
	registerUser::printForm($thanks);
}

/**
 * prints the link to the register user page
 * 
 * @deprecated 2.0 – User registerUser::printLink() instead
 *
 * @param string $_linktext text for the link
 * @param string $prev text to insert before the URL
 * @param string $next text to follow the URL
 * @param string $class optional class
 */
function printRegisterURL($_linktext, $prev = '', $next = '', $class = NULL) {
	deprecationNotice(gettext('Use registerUser::printLink() instead'));
	registerUser::printLink($_linktext, $prev, $next, $class);
}

if (isset($_POST['register_user'])) {
	zp_register_filter('load_theme_script', 'registerUser::postProcessor');
}