<?php
namespace sashsvamir\recaptchav3;

use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\httpclient\Client as HttpClient;
use yii\validators\Validator;

/**
 * Recaptchav3 widget validator.
 * see reference class: /himiklab/yii2-recaptcha-widget/src/ReCaptchaValidator
 *
 * @author sashsvamir
 */
class Recaptchav3Validator extends Validator
{
	const API_URL = 'https://www.google.com/recaptcha/api/siteverify';

	/** @var boolean Whether to skip this validator if the input is empty. */
	public $skipOnEmpty = YII_ENV_TEST; // skip empty value on test env

	/** @var string The shared key between your site and ReCAPTCHA. */
	public $secretKey;

	/** @var \yii\httpclient\Request */
	public $httpClientRequest;

	/** @var double */
	public $minScore;

	/** @var bool */
	public $enableClientValidation = false;

	public $message = 'Не удалось пройти проверку на бота. Попробуйте отправить форму еще раз.';

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		// validate only if no other errors
        $this->when = function ($model) {
            return !$model->hasErrors();
        };

		$this->secretKey = $this->secretKey ? : Yii::$app->params['google.recaptcha3.secret'];

		if (!$this->secretKey) {
			throw new InvalidConfigException('Required `secretKey` param isn\'t set.');
		}

		// todo: set key in config is better?
		// if (empty($this->secretKey)) {
		// 	/** @var ReCaptcha $reCaptcha */
		// 	$reCaptcha = Yii::$app->reCaptcha;
		// 	if ($reCaptcha && !empty($reCaptcha->secretKey)) {
		// 		$this->secretKey = $reCaptcha->secretKey;
		// 	} else {
		// 		throw new InvalidConfigException('Required `secretKey` param isn\'t set.');
		// 	}
		// }

		$this->httpClientRequest = (new HttpClient())->createRequest();
	}

	/**
	 * @inheritdoc
	 */
	// public function validateAttribute($model, $attribute)
	// {
	// 	// validate only if no errors
	// 	if (!$model->hasErrors()) {
	// 		parent::validateAttribute($model, $attribute);
	// 	}
	// }


	/**
	 * @param string $value Сюда должен попасть ключ из ответа google-api на запрос во фронте
	 * @inheritdoc
	 */
	public function validateValue($value = null)
	{
		if (!$value) {
			return ['Captcha response is empty!', null];
		}

		$response = $this->getResponse($value);

		if (!isset($response['success'])) {
			return ['Invalid recaptcha verify response: response not contain `success` field.', null];
		}

		if (false === (boolean)$response['success']) {
			$responseErrorCodes = isset($response['error-codes']) ? ' Response error-codes: ' . implode(', ', $response['error-codes']) : '';
			return [$this->message . $responseErrorCodes, null];
		}

		if (isset($response['score'])) {
			// save captcha score to params for saving later in model, see: beforeSave()
			Yii::$app->params['google.recaptcha3.response.score'] = $response['score'];
		}

		if ($this->minScore) {
            if ($response['score'] < $this->minScore) {
                return [$this->message . ' You have less score.', null];
            }
        }

		return null; // success
	}

	/**
	 * @param string $value
	 * @return array
	 * @throws Exception
	 * @throws \yii\base\InvalidParamException
	 */
	protected function getResponse($value)
	{
		if (YII_ENV_TEST || !(Yii::$app instanceof \yii\web\Application)) {
			// test mock
			return [
				'success' => true,
				'score' => 0.777,
			];
		}

		$data = [
			'secret' => $this->secretKey,
			'response' => $value,
			'remoteip' => Yii::$app->request->userIP,
		];
		$response = $this->httpClientRequest
			->setMethod('GET')
			->setUrl(self::API_URL)
			->setData($data)
			->send();
		if (!$response->isOk) {
			throw new Exception('Unable connection to the captcha server. Status code ' . $response->statusCode);
		}

		return $response->data;
	}


}
