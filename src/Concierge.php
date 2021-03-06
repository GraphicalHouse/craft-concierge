<?php

namespace olivierbon\concierge;

use Craft;
use craft\events\PluginEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterEmailMessagesEvent;
use craft\services\Dashboard;
use craft\services\Elements;
use craft\events\ElementEvent;
use craft\base\ElementAction;
use craft\base\ElementActionInterface;
use craft\base\ElementInterface;
use craft\events\ElementActionEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\ElementHelper;
use craft\services\Plugins;
use craft\services\SystemMessages;
use craft\services\Users;
use craft\web\Request;
use olivierbon\concierge\models\Settings;
use yii\base\Event;

/**
 * Concierge Class
 *
 * @author    Olivier Bon
 * @package   Concierge
 * @since     2.0.0
 *
 */
class Concierge extends \craft\base\Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var Concierge
     */
    public static $plugin;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public $hasCpSection = true;


    public function init()
    {
        parent::init();

        // Registers the ConciergeMailer service so it's available
        $this->setComponents([
            'mailer' => \olivierbon\concierge\services\ConciergeMailer::class,
        ]);

        // Registers the Concierge Status Widget
        self::registerConciergeStatusWidget();

        // Registers the Concierge Message
        self::registerConciergeMessages();

        // Avoids triggering Concierge when users are created in the CP
        if (Craft::$app->user->getIsGuest()) {
            // Listens for EVENT_AFTER_SAVE_ELEMENT and element instanceof User
            self::newUserElementIsSaved();
        }

        // Listens for EVENT_AFTER_UNSUSPEND_USER
        self::userIsUnsuspended();

        // NEW: Listens for EVENT_BEFORE_SAVE_ELEMENT
        self::beforeNewUserElementIsSaved();
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): string
    {
        // Get and pre-validate the settings
        $settings = $this->getSettings();
        $settings->validate();

        return \Craft::$app->getView()->renderTemplate('concierge/settings', [
            'settings' => $settings
        ]);
    }

    /**
     * Registers the moderation & notification messages Concierge needs
     */
    protected static function registerConciergeMessages()
    {
        Event::on(
            SystemMessages::class,
            SystemMessages::EVENT_REGISTER_MESSAGES,
            function(RegisterEmailMessagesEvent $event) {
                $event->messages[] = [
                    'key' => 'concierge_moderation',
                    'heading' => Craft::t('concierge', 'concierge_moderation_heading'),
                    'subject' => Craft::t('concierge', 'concierge_moderation_subject'),
                    'body' => Craft::t('concierge', 'concierge_moderation_body')
                ];

                $event->messages[] = [
                    'key' => 'concierge_activated',
                    'heading' => Craft::t('concierge', 'concierge_activated_heading'),
                    'subject' => Craft::t('concierge', 'concierge_activated_subject'),
                    'body' => Craft::t('concierge', 'concierge_activated_body')
                ];

                $event->messages[] = [
                    'key' => 'concierge_activated_normal',
                    'heading' => Craft::t('concierge', 'concierge_activated_heading_normal'),
                    'subject' => Craft::t('concierge', 'concierge_activated_subject_normal'),
                    'body' => Craft::t('concierge', 'concierge_activated_body_normal')
                ];

                $event->messages[] = [
                    'key' => 'concierge_mod_notification',
                    'heading' => Craft::t('concierge', 'concierge_mod_notification_heading'),
                    'subject' => Craft::t('concierge', 'concierge_mod_notification_subject'),
                    'body' => Craft::t('concierge', 'concierge_mod_notification_body')
                ];
            }
        );
    }

    /**
     * Registers the Concierge Status Widget
     */
    protected static function registerConciergeStatusWidget()
    {
        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = \olivierbon\concierge\widgets\ConciergeStatus::class;
            }
        );
    }

    /**
     * Listens for a new element to be saved then checks it's an instance of \craft\elements\User
     */
    protected function newUserElementIsSaved()
    {
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function(Event $event) {
                if ($event->element instanceof \craft\elements\User) {
                    $user = $event->element;
                    $isNew = $event->isNew;

                    // If it's a new user, suspend the user and issue enabled messages
                    if($isNew) {
                        Craft::$app->users->suspendUser($user);
                        if ($this->settings->concierge_moderation_enabled) {
                            Concierge::getInstance()->mailer->sendAwaitingModerationEmail($user);
                        }

                        if($this->settings->concierge_mod_notification_enabled) {
                            Concierge::getInstance()->mailer->sendNewUserRegistrationEmail();
                        }
                    }
                }
            }
        );
    }

    /**
     * Listens for a new element to be saved then checks it's an instance of \craft\elements\User
     */
    protected function beforeNewUserElementIsSaved()
    {
        Event::on(
            Elements::class,
            Elements::EVENT_BEFORE_SAVE_ELEMENT,
            function(Event $event) {
                if ($event->element instanceof \craft\elements\User) {
                    $user = $event->element;
                    $email    = $user->email;
                    $company = $user->getFieldValue('company');
                    $isNew = $event->isNew;

                    // If it's a new user, check their email domain is not blacklisted
                    if($isNew) {
                      if (($company == '') or (strpos($email, 'qq.com') !== false)) {
                        // Cancel the user creation
                        // TODO: fix this so it doesn't just throw an error
                        $event->isValid = false;
                      }
                    }
                }
            }
        );
    }

    /**
     * Listens for a user to be unsuspended and issue messages if enabled
     */
    protected function userIsUnsuspended()
    {
        Event::on(
            Users::class,
            Users::EVENT_AFTER_UNSUSPEND_USER,
            function(Event $event) {
                if ($this->settings->concierge_activated_enabled) {
                  if ($event->user->isInGroup('customerContract')) {
                    Concierge::getInstance()->mailer->sendUserUnsuspendedEmail($event->user);
                  }
                  if ($event->user->isInGroup('customerNormal')) {
                    Concierge::getInstance()->mailer->sendUserUnsuspendedNormalEmail($event->user);
                  }
                }
            }
        );
    }
}
