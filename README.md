
# Installation




Install package:
```sh
composer require sashsvamir/yii2-recaptchav3:"dev-master"
```


Add validator to model:

```php
public $verifyCode;

public function rules() {
    return [
		['verifyCode', Recaptchav3Validator::class],
		// or
		['verifyCode', Recaptchav3Validator::class, 'minScore' => 0.3, 'message' => 'You are bot!'],
    ];
}
```



Add widget field to view:

```php
$form = ActiveForm::begin(...);
echo $form->field($model, 'verifyCode')->widget(Recaptchav3Widget::class);
```



#### Note:
after success response on google request (in backend), recaptcha validator save response `score` to `Yii::$app->params['google.recaptcha3.response.score']`,
so that you can get `score` after success validation for saving to db.

