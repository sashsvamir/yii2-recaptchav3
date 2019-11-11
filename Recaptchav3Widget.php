<?php
namespace sashsvamir\recaptchav3;

use Yii;
use yii\base\InvalidConfigException;
use yii\widgets\InputWidget;

/**
 * Yii2 Google recaptcha v3 widget.
 *
 * For example:
 *
 * ```php
 * <?= $form->field($model, 'Recaptchav3')->widget(Recaptchav3::className()) ?>
 * // or:
 * <?= Recaptchav3::widget(['name' => 'recaptcha', 'publicKey' => 'public key']) ?>
 * ```
 *
 * @see https://developers.google.com/recaptcha
 * @author sashsvamir
 */
class Recaptchav3Widget extends InputWidget
{
	const API_URL = 'https://www.google.com/recaptcha/api.js';

	/** @var string Recaptcha v3 public key */
	public $publicKey;

	public $requestAction = 'feedback_form';

    public $options = [
        'value' => '', // on every render attribute value should be empty (to prevent "timeout-or-duplicate" error)
    ];

	/**
	 * @inheritdoc
	 */
	public function init() {
		parent::init();

		$this->publicKey = $this->publicKey ? : Yii::$app->params['google.recaptcha3.public'];

		if (!$this->publicKey) {
			throw new InvalidConfigException('Required `publicKey` param isn\'t set.');
		}
	}

	/**
	 * @inheritdoc
	 */
	public function run()
	{
		$this->field->label(false);

		$arguments = http_build_query([
			'render' => $this->publicKey,
		]);

		$view = $this->view;

		$depends = ['yii\web\JqueryAsset'];

		$view->registerJsFile(self::API_URL . '?' . $arguments, ['position' => $view::POS_END, 'depends' => $depends], 'recaptchav3');

		$formId = $this->field->form->getId();
		$inputId = $this->options['id'];

        // Работы Recaptcha3Widget формы:
        // - событие beforeSubmit (см yii.activeForm.js) вызывается после валидации формы, но перед отправкой формы
        // - чтобы прервать/продолжить отправку формы, необходимо вернуть false/true из последнего обработчика 'beforeSubmit'
        // - обработчики события beforeSubmit исполняются поочереди (в порядке прикрепления к событию)
        // - используем класс 'ajax-loading' формы как индикатор загрузки гугл токена
        //
        // Как использовать данный виджет на ajax страницах:
        // - после отправки формы будет вызвано событие 'beforeSubmit' несколько раз:
        //   1. в момент получения токена
        //   2. после получения токена
        // поэтому:
        // - вешаем на форму обработчик 'beforeSubmit' события в котором:
        //   - если у формы стоит класс 'ajax-loading' пропускаем обработку (токен а процессе получения)
        // - иначе, возвращяем из обработчика 'false' (чтобы предотвратить нативную отправку) и делаем ajax запрос используя данные формы

        $js = /** @lang JavaScript */"			
			var form = $('#{$formId}'); // get activeForm
            var attr = form.find('#{$inputId}'); // get attribute field

			// add event handler (before yii js submit the form, prepare recaptcha token from google)
			form.on('beforeSubmit', function (e) {
			    // console.log('widget: beforeSubmit')

				form.addClass('ajax-loading'); // show ajax indicator, also this leads to stop ajax submit of frame form

				// if response (token) already was recieved, pass to next event handler
				if (attr.val() !== '') {
					form.removeClass('ajax-loading');
					return true;
				}

				// fetch captcha token and call submit
				// not support IE9 and less, you can use: if (window.ie9le) { ... }
                grecaptcha.ready(function () {
                    grecaptcha.execute('{$this->publicKey}', { action: '{$this->requestAction}' })
                        .then(function (token) {
                            form.removeClass('ajax-loading'); // hide ajax indicator
                            attr.val(token);

                            // console.log('widget: submit')
                            // submit form (also this leads to perform activeForm validation)
                            form.submit();
                        });
                });

				return false; // stop activeForm submitting
			});
		";
		$view->registerJs($js, $view::POS_END);

		return $this->renderInputHtml('hidden');
	}

}
