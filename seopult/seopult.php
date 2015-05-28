<?php
/**
 * 2015 SeoPult
 * @author    SeoPult RU <https://seopult.ru/>
 * @copyright 2015 SeoPult RU
 * @license   GNU General Public License, version 2
 */
if (!defined('_PS_VERSION_')) exit;

class Seopult extends Module {

	public $errors = array();
	public $succ = array();

	public function __construct()
	{
		$this->name = 'seopult';
		$this->tab = 'seo';
		$this->config_form = 'password';
		$this->version = '1.0.0';
		$this->author = 'SeoPult';
		$this->need_instance = 1;
		$this->bootstrap = true;
		parent::__construct();
		$this->displayName = $this->l('SeoPult module');
		$this->description = $this->l('Сервис самостоятельного автоматизированного продвижения сайтов в ТОП10 поисковых систем Яндекс и Google');
		$this->confirmUninstall = $this->l('Вы действительно хотите удалить модуль SeoPult?');
	}

	public function install()
	{
		if (!Configuration::get('seopult_user_hash'))
		{
			$user_hash = substr(md5(_PS_VERSION_.Configuration::get('PS_SHOP_NAME').Tools::passwdGen()), 0, 32);
			Configuration::updateValue('seopult_user_hash', $user_hash);
		}
		Configuration::updateValue('seopult_password_cryptkey', '7zcnsutn5nuzbpwgmok6ayx64g8xt4rxgjgegndhvwxzwn46ni');
		Configuration::updateValue('seopult_password_hash', '2e6c482bdf113d28fc852436252c1fb9');
		if (!Configuration::get('seopult_crypt_key')) Configuration::updateValue('seopult_crypt_key');
		if (!Configuration::get('seopult_login')) Configuration::updateValue('seopult_login', 'prestashop_'.$_SERVER['SERVER_NAME']);
		include(dirname(__FILE__).'/sql/install.php');
		return parent::install() && $this->registerHook('header') && $this->registerHook('backOfficeHeader') && $this->registerHook('displayBackOfficeHeader') && $this->registerHook('displayHeader');
	}

	public function uninstall()
	{
		include(dirname(__FILE__).'/sql/uninstall.php');
		return parent::uninstall();
	}

	/**
	 * Load the configuration form
	 */
	public function getContent()
	{
		if (Configuration::get('seopult_crypt_key') == '' || Configuration::get('seopult_user_hash') == '')
		{
			//регистрируем пользователя если он не зарегистрирован в SeoPult
			$response = $this->register_seopult();
			$response = Tools::jsonDecode($response);
			if ($response->error == false)
			{
				Configuration::updateValue('seopult_crypt_key', $response->data->cryptKey);
			}
			else
			{
				//ник пользователя занят
				if ($response->status->code == '3')
				{
					Configuration::updateValue('seopult_login', 'prestashop_'.$_SERVER['SERVER_NAME'].'_'._PS_VERSION_);
					return $this->getContent();
				}
				//Если другая ошибка - выводим её
				$this->context->smarty->assign('error', $response->status->message);
				$html = $this->renderConfigurationForm();
				$this->context->smarty->assign('html', $html);
				$output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
				return $output;
			}
		}
		else
		{
			//нажали на кнопку восстановление пароля и ввели email
			if (Tools::isSubmit('submitPassword') && (Tools::getValue('passwordemail')))
			{
				Configuration::updateValue('seopult_email', Tools::getValue('passwordemail'));
				$data = array('email' => Tools::getValue('passwordemail'), 'createdOn' => date('Y-m-d h:i:s'));
				$k = json_encode($data);
				$code = $this->encrypt($k, Configuration::get('seopult_password_cryptkey'));
				$url = 'http://test.seopult.pro/partners/uSeo/sendRestoreCode?k=zaa'.Configuration::get('seopult_password_hash').urlencode($code);
				$result = $this->getRequestUri($url);
				$result = Tools::jsonDecode($result);
				if ($result->status->code == '13')
				{
					$options = array();
					foreach ($result->data as $account)
					{
						$options[] = array("id_user" => $account->id, "name" => (!empty($account->projectDomain) ? $account->projectDomain : $account->suggestedDomain).' ('.$account->id.')',);
					}
					return $this->renderCorrectUser($options);
				}
				elseif ($result->status->code == '0')
				{
					$this->succ = '';
					$this->succ = $this->l('На указанный Email выслан Код подтверждения');
				}
			}
			//нажали на кнопку восстановление пароля и но не ввели email
			elseif (Tools::isSubmit('submitPassword') && Tools::getValue('passwordemail') == '' && !(Tools::isSubmit('SaveHash'))) $this->errors = $this->l('Не заполнено обязательно поле E-mail');
			//нажали на кнопку подтверждения аккаунта пользователя
			if (Tools::isSubmit('submitCurrent'))
			{
				$data = array('email' => Configuration::get('seopult_email'), 'id' => Tools::getValue('users'), 'createdOn' => date('Y-m-d h:i:s'));
				$k = json_encode($data);
				$code = $this->encrypt($k, Configuration::get('seopult_password_cryptkey'));
				$url = 'http://test.seopult.pro/partners/uSeo/sendRestoreCode?k=zaa'.Configuration::get('seopult_password_hash').urlencode($code);
				$result = $this->getRequestUri($url);
				$result = Tools::jsonDecode($result);
				//если ошибок нет, выводим сообщение, что письмо отправленно, в противном случае выводим ошибку
				if ($result->error == false)
				{
					$this->succ = '';
					$this->succ = $this->l('На указанный Email выслан Код подтверждения');
				}
				else
				{
					$this->errors = $result->status->message;
					$this->context->smarty->assign('error', $this->errors);
				}
			}
			//нажали на кнопку Готово, и не ввели email
			//уточнение аккаунта при восстановлении пароля
			if (Tools::isSubmit('SaveHash'))
			{
				if (Tools::getValue('password'))
				{
					//восстановление доступа без уточнения, если 1 учётная запись
					$data = array('email' => Configuration::get('seopult_email'), 'restoreCode' => Tools::getValue('password'), 'createdOn' => date('Y-m-d h:i:s'));
					$k = json_encode($data);
					$code = $this->encrypt($k, Configuration::get('seopult_password_cryptkey'));
					$url = 'http://test.seopult.pro/partners/uSeo/getAccountCredentials?k=zaa'.Configuration::get('seopult_password_hash').urlencode($code);
					$result = $this->getRequestUri($url);
					$result = Tools::jsonDecode($result);
					//успешное восстановление доступа к аккаунту SEOPult
					if ($result->error == false)
					{
						Configuration::updateValue('seopult_user_hash', $result->data->hash);
						Configuration::updateValue('seopult_crypt_key', $result->data->cryptKey);
						Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name);
					}
					else
					{
						$this->errors = $result->status->message;
						$this->context->smarty->assign('error', $this->errors);
					}
				}
			}
			//нажали кнопку воссатновления пароля
			if (Tools::isSubmit('password'))
			{
				return $this->renderPassword();
			}
			else
			{
				$this->_postProcess();
				$html = $this->renderConfigurationForm();
				$this->context->smarty->assign('html', $html);
				$output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
				return $output;
			}
		}
	}

	public function renderCorrectUser($options)
	{
		$inputs = array('type' => 'select', 'label' => $this->l('Аккаунт SeoPult'), 'name' => 'users', 'options' => array('query' => $options, 'id' => 'id_user', 'name' => 'name'), 'desc' => 'Выберите Аккаунт SeoPult, доступ к которому необходимо восстановить<br/><br/>
						<button class="btn btn-default button button-medium exclusive" type="submit" id="submitCurrent" name="submitCurrent" style="font-style: normal; background-color: #00aff0;  box-shadow: none;">
							<span>Подтвердить<i class="icon-mail-reply right"></i></span>
						</button>');
		$fields_form = array('form' => array('legend' => array('title' => 'Уточнение учётной записи SeoPult', 'icon' => 'icon-cogs'), 'input' => array($inputs)));
		$helper = new HelperForm();
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', true).'&configure='.urlencode($this->name).'&password';
		$html = $helper->generateForm(array($fields_form));
		return $html;
	}

	public function renderPassword()
	{
		$inputs[0] = array('type' => 'text', 'label' => 'E-mail', 'required' => true, 'desc' => 'Введите email Администратора сайта для восстановления доступа к SeoPult<br/><br/>
<button class="btn btn-default button button-medium exclusive" type="submit" id="SubmitPassword" name="SubmitPassword"
style="font-style: normal; background-color: #00aff0;  box-shadow: none;">
							<span>
							Оправить пиcьмо
								<i class="icon-mail-reply right"></i>
							</span>
						</button>', 'name' => 'passwordemail', 'col' => '4');
		$inputs[1] = array('type' => 'text', 'label' => 'Код подтверждения', 'desc' => 'Введите сюда полученный код в письме<br/><br/>
<button class="btn btn-default" type="submit" id="SaveHash" name="SaveHash">'.$this->l('Готово!').'<i class="process-icon-save"></i>
						</button>', 'name' => 'password', 'col' => '4',);
		$fields_form = array('form' => array('legend' => array('title' => 'Восстановление доступа к SeoPult', 'icon' => 'icon-cogs'), 'input' => $inputs,));
		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$this->fields_form = array();
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitPassword';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', true).'&configure='.urlencode($this->name).'&password';
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$html = $helper->generateForm(array($fields_form));
		if (count($this->errors)) $html = '<div class="module_error alert alert-danger">
			<button type="button" class="close" data-dismiss="alert">×</button>
			'.$this->errors.'
		</div>'.$html;
		if (count($this->succ)) $html = '<div class="module_error alert alert-info">
			<button type="button" class="close" data-dismiss="alert">×</button>
			'.$this->succ.'
		</div>'.$html;
		return $html;
	}

	public function renderConfigurationForm()
	{
		$url = $this->context->link->getAdminLink('AdminModules', true).'&configure='.urlencode($this->name).'&password';
		return $url;
	}

	static public function encrypt($string, $key = '%key&')
	{
		$result = '';
		for ($i = 0;$i < strlen($string);$i++)
		{
			$char = substr($string, $i, 1);
			$keychar = substr($key, ($i % strlen($key)) - 1, 1);
			$ordChar = ord($char);
			$ordKeychar = ord($keychar);
			$sum = $ordChar + $ordKeychar;
			$char = chr($sum);
			$result .= $char;
		}
		return base64_encode($result);
	}

	/**
	 * Save form data.
	 */
	protected function _postProcess()
	{
		$login = Configuration::get('seopult_login');
		if (Configuration::get('seopult_user_hash') == '')
		{
			$user_hash = substr(md5(_PS_VERSION_.Configuration::get('PS_SHOP_NAME').Tools::passwdGen()), 0, 32);
			Configuration::updateValue('seopult_user_hash', $user_hash);
		}
		$data = array('login' => $login, 'hash' => Configuration::get('seopult_user_hash'), 'createdOn' => date('Y-m-d h:i:s'), 'cms' => 'prestashop');
		//И далее генерируем URL:
		$k = json_encode($data);
		$code = $this->encrypt($k, Configuration::get('seopult_crypt_key'));
		$url = 'http://test.seopult.pro/iframe/cryptLogin?k=zaa'.Configuration::get('seopult_user_hash').urlencode($code);
		$this->context->smarty->assign('url', $url);
	}

	/**
	 * Регистрирует пользователя при входе в админку
	 */
	public function register_seopult()
	{
		$response = '';
		//LOGIN - Уникальный логин в системе. Для поддержания уникальности желательно в него добавлять префикс, содержащий ид пользователя партнера.
		$login = Configuration::get('seopult_login');
		if (empty($login)) $login = 'prestashop_'.$_SERVER['SERVER_NAME'];
		//EMAIL - Емеил пользователя в системе. Необязательный параметр.
		$email = Configuration::get('SEOPULT_ACCOUNT_EMAIL', '');
		$url = $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
		$url = urlencode($url);
		//USER_HASH - Уникальный 32-символьный случайный хеш пользователя. При этом его необходимо сохранить в БД для дальнейшего использования. Формат: md5 код.
		$user_hash = Configuration::get('seopult_user_hash');
		//PARTNER_HASH - Ваш уникальный 32-символьный идентификатор партнера. Формат: md5 код.
		//SUGGESTED_DOMAIN - Домен, который должен продвигаться в системе. Без протокола. Например: site.ru, lenta.ru, cnn.com
		$partner_hash = Configuration::get('seopult_password_hash') ? Configuration::get('seopult_password_hash') : '2e6c482bdf113d28fc852436252c1fb9';
		$suggested_domain = $_SERVER['SERVER_NAME'];
		$request = 'http://test.seopult.pro/iframe/getCryptKeyWithUserReg?login='.$login.'&url='.$url.'&email='.$email.'&hash='.$user_hash.'&partner='.$partner_hash.'&suggestedDomain='.$suggested_domain;
		return $this->getRequestUri($request);
	}

	public function getRequestUri($request)
	{
		if (function_exists('curl_init'))
		{
			if (in_array('curl', get_loaded_extensions()))
			{
				$c = curl_init($request);
				curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
				$response = curl_exec($c);
				curl_close($c);
			}
			elseif (function_exists('file_get_contents') && ini_get('allow_url_fopen')) $response = Tools::file_get_contents($request);
		}
		else
		{
			$this->errors = $this->l('Ошибка: "culr" на сервере не обнаружен');
			return false;
		}
		return $response;
	}

	/**
	 * Add the CSS & JavaScript files you want to be loaded in the BO.
	 */
	public function hookBackOfficeHeader()
	{
		if (Tools::getValue('module_name') == $this->name)
		{
			$this->context->controller->addJS($this->_path.'js/back.js');
			$this->context->controller->addCSS($this->_path.'css/back.css');
		}
	}

	/**
	 * Add the CSS & JavaScript files you want to be added on the FO.
	 */
	public function hookHeader()
	{
		$this->context->controller->addJS($this->_path.'/js/front.js');
		$this->context->controller->addCSS($this->_path.'/css/front.css');
	}

	public function hookDisplayBackOfficeHeader()
	{
		/* Place your code here. */
	}

	public function hookDisplayHeader()
	{
		/* Place your code here. */
	}
}
