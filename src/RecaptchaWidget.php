<?php

namespace giantbits\crelish\recaptcha;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\widgets\InputWidget;

/**
 * RecaptchaWidget renders a hidden input for Google reCAPTCHA v3
 *
 * The widget automatically handles token refresh on form submission,
 * solving the common issue of expired tokens with AJAX/PJAX forms.
 *
 * Usage in ActiveForm:
 * ```php
 * echo $form->field($model, 'recaptcha')->widget(RecaptchaWidget::class);
 * ```
 *
 * Standalone usage:
 * ```php
 * echo RecaptchaWidget::widget([
 *     'name' => 'recaptcha',
 *     'action' => 'contact_form',
 * ]);
 * ```
 */
class RecaptchaWidget extends InputWidget
{
    /**
     * @var string reCAPTCHA v3 action name. Used for analytics in Google console.
     * Should be alphanumeric with underscores. e.g., 'contact_form', 'login', 'signup'
     */
    public $action = 'form_submit';

    /**
     * @var string|null Site key. If null, will be loaded from recaptcha component.
     */
    public $siteKey;

    /**
     * @var string Component name in Yii application
     */
    public $componentName = 'recaptcha';

    /**
     * @var string reCAPTCHA API URL
     */
    public $apiUrl = 'https://www.google.com/recaptcha/api.js';

    /**
     * @var bool Whether to render the field wrapper (set false when using with ActiveForm)
     */
    public $renderWrapper = false;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        // Try to get config from component
        if ($this->siteKey === null) {
            $component = Yii::$app->get($this->componentName, false);
            if ($component instanceof RecaptchaConfig) {
                $this->siteKey = $component->siteKey;
                if (empty($this->apiUrl)) {
                    $this->apiUrl = $component->apiUrl;
                }
            }
        }

        if (empty($this->siteKey)) {
            throw new InvalidConfigException(
                'RecaptchaWidget::$siteKey must be set or configure the recaptcha component.'
            );
        }

        // Sanitize action name (alphanumeric and underscores only)
        $this->action = preg_replace('/[^a-zA-Z0-9_]/', '_', $this->action);
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $this->registerAssets();

        $inputId = $this->getInputId();
        $inputName = $this->getInputName();

        $input = Html::hiddenInput($inputName, '', [
            'id' => $inputId,
            'data-recaptcha' => 'true',
            'data-sitekey' => $this->siteKey,
            'data-action' => $this->action,
        ]);

        if ($this->renderWrapper) {
            return Html::tag('div', $input, ['class' => 'recaptcha-widget']);
        }

        return $input;
    }

    /**
     * Get the input ID
     * @return string
     */
    protected function getInputId()
    {
        if ($this->hasModel()) {
            return Html::getInputId($this->model, $this->attribute);
        }
        return $this->getId() . '-recaptcha';
    }

    /**
     * Get the input name
     * @return string
     */
    protected function getInputName()
    {
        if ($this->hasModel()) {
            return Html::getInputName($this->model, $this->attribute);
        }
        return $this->name;
    }

    /**
     * Register JavaScript and CSS assets
     */
    protected function registerAssets()
    {
        $view = $this->getView();
        $siteKey = Json::encode($this->siteKey);

        // Register reCAPTCHA API script
        $view->registerJsFile(
            $this->apiUrl . '?render=' . $this->siteKey,
            ['position' => \yii\web\View::POS_HEAD]
        );

        // Register the form handler script
        $js = <<<JS
(function() {
    if (window.CrelishRecaptcha) return;

    window.CrelishRecaptcha = {
        siteKey: {$siteKey},

        getToken: function(action) {
            return new Promise(function(resolve, reject) {
                if (typeof grecaptcha === 'undefined') {
                    reject(new Error('grecaptcha not loaded'));
                    return;
                }

                grecaptcha.ready(function() {
                    grecaptcha.execute(window.CrelishRecaptcha.siteKey, {action: action})
                        .then(resolve)
                        .catch(reject);
                });
            });
        },

        handleForm: function(form) {
            if (form.dataset.recaptchaHandled) return;
            form.dataset.recaptchaHandled = 'true';

            var input = form.querySelector('input[data-recaptcha="true"]');
            if (!input) return;

            var action = input.dataset.action || 'form_submit';

            form.addEventListener('submit', function(e) {
                // If already processing, skip
                if (form.dataset.recaptchaProcessing === 'true') {
                    return true;
                }

                // If token is fresh (set within last second), allow submit
                if (form.dataset.recaptchaFresh === 'true') {
                    form.dataset.recaptchaFresh = 'false';
                    return true;
                }

                e.preventDefault();
                e.stopImmediatePropagation();

                form.dataset.recaptchaProcessing = 'true';

                // Disable submit button
                var submitBtn = form.querySelector('[type="submit"]');
                var originalText = '';
                if (submitBtn) {
                    originalText = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="recaptcha-loading">Verifying...</span>';
                }

                window.CrelishRecaptcha.getToken(action)
                    .then(function(token) {
                        input.value = token;
                        form.dataset.recaptchaProcessing = 'false';
                        form.dataset.recaptchaFresh = 'true';

                        // Re-enable button
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }

                        // Trigger native submit or PJAX submit
                        if (typeof jQuery !== 'undefined' && jQuery.pjax) {
                            var pjaxContainer = form.closest('[data-pjax-container]') ||
                                               form.closest('[data-pjax]')?.closest('[id]');
                            if (pjaxContainer || form.dataset.pjax) {
                                jQuery.pjax.submit(jQuery(form), '#' + (pjaxContainer?.id || 'pjax-container'));
                                return;
                            }
                        }

                        // Regular submit
                        form.submit();
                    })
                    .catch(function(err) {
                        console.error('reCAPTCHA error:', err);
                        form.dataset.recaptchaProcessing = 'false';

                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }

                        alert('reCAPTCHA verification failed. Please try again.');
                    });
            }, true); // Use capture phase to run before other handlers
        },

        init: function() {
            // Handle existing forms
            document.querySelectorAll('form').forEach(function(form) {
                if (form.querySelector('input[data-recaptcha="true"]')) {
                    window.CrelishRecaptcha.handleForm(form);
                }
            });

            // Handle dynamically added forms (for PJAX)
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
                            var forms = node.tagName === 'FORM' ? [node] : node.querySelectorAll?.('form') || [];
                            forms.forEach(function(form) {
                                if (form.querySelector('input[data-recaptcha="true"]')) {
                                    window.CrelishRecaptcha.handleForm(form);
                                }
                            });
                        }
                    });
                });
            });

            observer.observe(document.body, {childList: true, subtree: true});
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.CrelishRecaptcha.init);
    } else {
        window.CrelishRecaptcha.init();
    }
})();
JS;

        $view->registerJs($js, \yii\web\View::POS_END, 'crelish-recaptcha');
    }
}
