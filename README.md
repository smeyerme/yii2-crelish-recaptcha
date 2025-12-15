# Yii2 Crelish reCAPTCHA v3

Google reCAPTCHA v3 integration for Yii2 and Crelish CMS.

## Features

- **reCAPTCHA v3 support** - Invisible verification, no user interaction required
- **Automatic token refresh** - Solves the common expired token issue with AJAX/PJAX forms
- **Score-based validation** - Configure minimum score threshold
- **PJAX compatible** - Works seamlessly with Yii2 PJAX widgets
- **Simple integration** - Just add widget and validator to your forms

## Installation

```bash
composer require giantbits/yii2-crelish-recaptcha
```

## Configuration

Add the component to your application config:

```php
'components' => [
    'recaptcha' => [
        'class' => \giantbits\crelish\recaptcha\RecaptchaConfig::class,
        'siteKey' => 'YOUR_RECAPTCHA_V3_SITE_KEY',
        'secret' => 'YOUR_RECAPTCHA_V3_SECRET_KEY',
        'scoreThreshold' => 0.5, // optional, default 0.5 (0.0 = bot, 1.0 = human)
    ],
]
```

Or use environment variables:

```php
'recaptcha' => [
    'class' => \giantbits\crelish\recaptcha\RecaptchaConfig::class,
    'siteKey' => $_ENV['RECAPTCHA_SITE_KEY'],
    'secret' => $_ENV['RECAPTCHA_SECRET_KEY'],
],
```

## Usage

### 1. Add the attribute to your model

```php
class ContactForm extends Model
{
    public $name;
    public $email;
    public $message;
    public $recaptcha; // Add this attribute

    public function rules()
    {
        return [
            [['name', 'email', 'message'], 'required'],
            ['email', 'email'],
            // Add the validator
            ['recaptcha', \giantbits\crelish\recaptcha\RecaptchaValidator::class],
        ];
    }
}
```

### 2. Add the widget to your form

**With ActiveForm (PHP):**

```php
<?php $form = ActiveForm::begin(); ?>

<?= $form->field($model, 'name') ?>
<?= $form->field($model, 'email') ?>
<?= $form->field($model, 'message')->textarea() ?>

<?= $form->field($model, 'recaptcha')->widget(
    \giantbits\crelish\recaptcha\RecaptchaWidget::class,
    ['action' => 'contact_form']
)->label(false) ?>

<?= Html::submitButton('Submit') ?>

<?php ActiveForm::end(); ?>
```

**With Twig (Crelish CMS):**

```twig
{{ use('giantbits/crelish/recaptcha/RecaptchaWidget') }}

{% set form = active_form_begin({'id': 'contact-form'}) %}

{{ form.field(model, 'name') | raw }}
{{ form.field(model, 'email') | raw }}
{{ form.field(model, 'message').textarea() | raw }}

{{ form.field(model, 'recaptcha').widget(recaptcha, {'action': 'contact_form'}).label(false) | raw }}

{{ html.submitButton('Submit', {'class': 'btn btn-primary'}) | raw }}

{{ active_form_end() }}
```

### 3. Works with PJAX

The widget automatically handles PJAX forms. No additional configuration needed:

```php
<?php Pjax::begin(['id' => 'contact-form-pjax']); ?>

<?php $form = ActiveForm::begin(['options' => ['data-pjax' => true]]); ?>
    // ... form fields ...
    <?= $form->field($model, 'recaptcha')->widget(RecaptchaWidget::class)->label(false) ?>
<?php ActiveForm::end(); ?>

<?php Pjax::end(); ?>
```

## Validator Options

```php
['recaptcha', RecaptchaValidator::class,
    'scoreThreshold' => 0.7,        // Override component threshold
    'action' => 'contact_form',      // Verify action matches (optional)
    'verifyHostname' => true,        // Verify hostname (optional)
]
```

## Widget Options

```php
$form->field($model, 'recaptcha')->widget(RecaptchaWidget::class, [
    'action' => 'contact_form',      // Action name for Google analytics
    'siteKey' => 'override-key',     // Override component siteKey (optional)
])
```

## How It Works

Unlike other reCAPTCHA v3 implementations that generate a token on page load (which expires after ~2 minutes), this package:

1. Intercepts form submission
2. Requests a fresh token from Google
3. Injects the token into the form
4. Submits the form with the valid token

This solves the common "token expired" issue, especially with AJAX/PJAX forms where users may take longer to fill out the form.

## Getting reCAPTCHA v3 Keys

1. Go to [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin)
2. Register a new site
3. Choose **reCAPTCHA v3**
4. Add your domains
5. Copy the Site Key and Secret Key

**Important:** reCAPTCHA v3 keys are different from v2 keys. Make sure you select v3 when creating your keys.

## License

MIT License