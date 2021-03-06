<?php

/**
 * @file
 * Contains \Drupal\sharethis\Form\SharethisConfigurationForm.
 */

namespace Drupal\sharethis\Form;

use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sharethis\SharethisManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityManagerInterface;

/**
 * Provides a settings for sharethis modle.
 */
class SharethisConfigurationForm extends ConfigFormBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The sharethis manager.
   *
   * @var \Drupal\sharethis\SharethisManagerInterface
   */
  protected $sharethisManager;

  /**
   * Constructs a \Drupal\user\SharethisConfigurationForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\sharethis\SharethisManagerInterface $sharethisManager
   *   The sharethis Manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, EntityManagerInterface $entity_manager, SharethisManagerInterface $sharethisManager) {
    parent::__construct($config_factory);

    $this->moduleHandler = $module_handler;
    $this->entityManager = $entity_manager;
    $this->sharethisManager = $sharethisManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('entity.manager'),
      $container->get('sharethis.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['sharethis.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sharethis_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    global $base_url;
    $my_path = drupal_get_path('module', 'sharethis');

    // First, setup variables we will need.
    // Get the path variables setup.
    // Load the css and js for our module's configuration.
    $config = $this->config('sharethis.settings');

    $current_options_array = $this->sharethisManager->getOptions();
    // Create the variables related to widget choice.
    $widget_type = $current_options_array['widget'];
    $widget_markup = "";
    if ($widget_type == "st_multi") {
      $widget_markup = "st_multi";
    }
    // Create the variables related to button choice.
    $button_choice = $current_options_array['buttons'];
    // Create the variables related to services chosen.
    $service_string = $current_options_array['services'];
    $service_string_markup = "";
    foreach (explode(",", $service_string) as $name => $string) {
      $key = explode(":", Unicode::substr($string, 0, -1));
      $key = $key[1];
      $service_string_markup[] = $key;
    }

    // Create the variables for publisher keys.
    $publisher = $current_options_array['publisherID'];
    // Create the variables for teasers.
    $form = array();
    $form['options'] = array(
      '#type' => 'fieldset',
      '#title' => t('Display'),
    );
    $form['options']['button_option'] = array(
      '#required' => TRUE,
      '#type' => 'radios',
      '#options' => array(
        'stbc_large' => t('Large Chicklets'),
        'stbc_' => t('Small Chicklets'),
        'stbc_button' => t('Classic Buttons'),
        'stbc_vcount' => t('Vertical Counters'),
        'stbc_hcount' => t('Horizontal Counters'),
        'stbc_custom' => t('Custom Buttons via CSS'),
      ),
      '#default_value' => $button_choice,
      '#title' => t("Choose a button style:"),
      '#prefix' => '<div class="st_widgetContain"><div class="st_spriteCover"><img id="stb_sprite" class="st_buttonSelectSprite ' . $button_choice . '" src="' . $base_url . '/' . $my_path . '/img/preview_sprite.png"></img></div><div class="st_widgetPic"><img class="st_buttonSelectImage" src="' . $base_url . '/' . $my_path . '/img/preview_bg.png" /></div>',
      '#suffix' => '</div>',
    );
    $form['options']['service_option'] = array(
      '#description' => t('<b>Add</b> a service by selecting it on the right and clicking the <i>left arrow</i>.  <b>Remove</b> it by clicking the <i>right arrow</i>.<br /><b>Change the order</b> of services under "Selected Services" by using the <i>up</i> and <i>down</i> arrows.'),
      '#required' => TRUE,
      '#type' => 'textfield',
      '#prefix' => '<div>',
      '#suffix' => '</div><div id="myPicker"></div>',
      '#title' => t("Choose Your Services."),
      '#default_value' => $service_string,
      '#maxlength' => 1024,
    );
    $form['options']['option_extras'] = array(
      '#title' => t('Extra services'),
      '#description' => t('Select additional services which will be available. These are not officially supported by ShareThis, but are available.'),
      '#type' => 'checkboxes',
      '#options' => [
        'Gplusone' => t('Google Plus One'),
        'Fblike' => t('Facebook Like'),
      ],
      '#default_value' => $config->get('option_extras'),
    );

    $form['options']['callesi'] = array(
      '#type' => 'hidden',
      '#default_value' => $current_options_array['callesi'],
    );

    $form['additional_settings'] = array(
      '#type' => 'vertical_tabs',
    );

    $form['context'] = array(
      '#type' => 'details',
      '#title' => t('Context'),
      '#group' => 'additional_settings',
      '#description' => t('Configure where the ShareThis widget should appear.'),
    );

    $form['context']['location'] = array(
      '#title' => t('Location'),
      '#type' => 'radios',
      '#options' => array(
        'content' => t('Node content'),
        'block' => t('Block'),
        'links' => t('Links area'),
      ),
      '#default_value' => $config->get('location'),
    );

    // Add an information section for each location type, each dependent on the
    // currently selected location.
    foreach (array('links', 'content', 'block') as $location_type) {
      $form['context'][$location_type]['#type'] = 'container';
      $form['context'][$location_type]['#states']['visible'][':input[name="location"]'] = array('value' => $location_type);
    }

    // Add help text for the 'content' location.
    $form['context']['content']['help'] = array(
      '#markup' => t('When using the Content location, you must place the ShareThis links in the <a href="@url">Manage Display</a> section of each content type.'),
      '#weight' => 10,
      '#prefix' => '<em>',
      '#suffix' => '</em>',
    );
    // Add help text for the 'block' location.
    $form['context']['block']['#children'] = 'You must choose which region to display the in from the Blocks administration';
    $entity_bundles = $this->entityManager->getBundleInfo('node');
    // Add checkboxes for each view mode of each bundle.
    $entity_modes = $this->entityManager->getViewModes('node');
    ;
    $modes = array();
    foreach ($entity_modes as $mode => $mode_info) {
      $modes[$mode] = $mode_info['label'];
    }
    // Get a list of content types and view modes.
    $view_modes_selected = $current_options_array['view_modes'];
    foreach ($entity_bundles as $bundle => $bundle_info) {
      $form['context']['links'][$bundle . '_options'] = array(
        '#title' => t('%label View Modes', array('%label' => $bundle_info['label'])),
        '#description' => t('Select which view modes the ShareThis widget should appear on for %label nodes.', array('%label' => $bundle_info['label'])),
        '#type' => 'checkboxes',
        '#options' => $modes,
        '#default_value' => $view_modes_selected[$bundle],
      );
    }
    // Allow the user to choose which content types will have ShareThis added
    // when using the 'Content' location.
    $content_types = array();
    $enabled_content_types = $current_options_array['node_types'];
    foreach ($entity_bundles as $bundle => $bundle_info) {
      $content_types[$bundle] = $this->t($bundle_info['label']);
    }

    $form['context']['content']['node_types'] = array(
      '#title' => $this->t('Node Types'),
      '#description' => $this->t('Select which node types the ShareThis widget should appear on.'),
      '#type' => 'checkboxes',
      '#options' => $content_types,
      '#default_value' => $enabled_content_types,
    );
    $form['context']['comments'] = array(
      '#title' => $this->t('Comments'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('comments'),
      '#description' => $this->t('Display ShareThis on comments.'),
      '#access' => $this->moduleHandler->moduleExists('comment'),
    );
    $sharethis_weight_list = array(-100, -50, -25, -10, 0, 10, 25, 50, 100);
    $form['context']['weight'] = array(
      '#title' => $this->t('Weight'),
      '#description' => $this->t('The weight of the widget determines the location on the page where it will appear.'),
      '#required' => FALSE,
      '#type' => 'select',
      '#options' => array_combine($sharethis_weight_list, $sharethis_weight_list),
      '#default_value' => $config->get('weight'),
    );
    $form['advanced'] = array(
      '#type' => 'details',
      '#title' => $this->t('Advanced'),
      '#group' => 'additional_settings',
      '#description' => $this->t('The advanced settings can usually be ignored if you have no need for them.'),
    );
    $form['advanced']['publisherID'] = array(
      '#title' => $this->t("Insert a publisher key (optional)."),
      '#description' => $this->t("When you install the module, we create a random publisher key.  You can register the key with ShareThis by contacting customer support.  Otherwise, you can go to <a href='http://www.sharethis.com/account'>ShareThis</a> and create an account.<br />Your official publisher key can be found under 'My Account'.<br />It allows you to get detailed analytics about sharing done on your site."),
      '#type' => 'textfield',
      '#default_value' => $publisher,
    );
    $form['advanced']['late_load'] = array(
      '#title' => $this->t('Late Load'),
      '#description' => $this->t("You can change the order in which ShareThis widget loads on the user's browser. By default the ShareThis widget loader loads as soon as the browser encounters the JavaScript tag; typically in the tag of your page. ShareThis assets are generally loaded from a CDN closest to the user. However, if you wish to change the default setting so that the widget loads after your web-page has completed loading then you simply tick this option."),
      '#type' => 'checkbox',
      '#default_value' => $config->get('late_load'),
    );
    $form['advanced']['twitter_suffix'] = array(
      '#title' => $this->t("Twitter Suffix"),
      '#description' => $this->t("Optionally append a Twitter handle, or text, so that you get pinged when someone shares an article. Example: <em>via @YourNameHere</em>"),
      '#type' => 'textfield',
      '#default_value' => $config->get('twitter_suffix'),
    );
    $form['advanced']['twitter_handle'] = array(
      '#title' => $this->t('Twitter Handle'),
      '#description' => $this->t('Twitter handle to use when sharing.'),
      '#type' => 'textfield',
      '#default_value' => $config->get('twitter_handle'),
    );
    $form['advanced']['twitter_recommends'] = array(
      '#title' => $this->t('Twitter recommends'),
      '#description' => $this->t('Specify a twitter handle to be recommended to the user.'),
      '#type' => 'textfield',
      '#default_value' => $config->get('twitter_recommends'),
    );
    $form['advanced']['option_onhover'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Display ShareThis widget on hover'),
      '#description' => $this->t('If disabled, the ShareThis widget will be displayed on click instead of hover.'),
      '#default_value' => $config->get('option_onhover'),
    );
    $form['advanced']['option_neworzero'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Display count "0" instead of "New"'),
      '#description' => $this->t('Display a zero (0) instead of "New" in the count for content not yet shared.'),
      '#default_value' => $config->get('option_neworzero'),
    );
    $form['advanced']['option_shorten'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Display short URL'),
      '#description' => $this->t('Display either the full or the shortened URL.'),
      '#default_value' => $config->get('option_shorten'),
    );
    $form['advanced']['cns'] = array(
      '#title' => $this->t('<b>CopyNShare </b><sup>(<a href="http://support.sharethis.com/customer/portal/articles/517332-share-widget-faqs#copynshare" target="_blank">?</a>)</sup>'),
      '#type' => 'checkboxes',
      '#prefix' => '<div id="st_cns_settings">',
      '#suffix' => '</div><div class="st_cns_container">
				<p>CopyNShare is the new ShareThis widget feature that enables you to track the shares that occur when a user copies and pastes your website\'s <u>URL</u> or <u>Content</u>. <br/>
				<u>Site URL</u> - ShareThis adds a special #hashtag at the end of your address bar URL to keep track of where your content is being shared on the web.<br/>
				<u>Site Content</u> - It enables the pasting of "See more: YourURL#SThashtag" after user copies-and-pastes text. When a user copies text within your site, a "See more: yourURL.com#SThashtag" will appear after the pasted text. <br/>
				Please refer the <a href="http://support.sharethis.com/customer/portal/articles/517332-share-widget-faqs#copynshare" target="_blank">CopyNShare FAQ</a> for more details.</p>
			</div>',
      '#options' => array(
        'donotcopy' => $this->t("Measure copy & shares of your site's Content"),
        'hashaddress' => $this->t("Measure copy & shares of your site's URLs"),
      ),
      '#default_value' => $config->get('cns'),
    );
    $form['#attached']['drupalSettings']['sharethis']['service_string_markup'] = $service_string_markup;
    $form['#attached']['library'][] = 'sharethis/drupal.sharethisform';
    $form['#attached']['library'][] = 'sharethis/drupal.sharethispicker';
    $form['#attached']['library'][] = 'sharethis/drupal.sharethispickerexternal';
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $input_values = $form_state->getUserInput();

    // Additional filters for the service option input.
    // Sanitize the publisher ID option.
    // Since it's a text field, remove anything that resembles code.
    $input_values['service_option'] = Xss::filter($input_values['service_option']);

    // Additional filters for the option extras input.
    $input_values['option_extras'] = (isset($input_values['option_extras'])) ? $input_values['option_extras'] : array();

    // Sanitize the publisher ID option.  Since it's a text field, remove anything that resembles code.
    $input_values['publisherID'] = Xss::filter($input_values['publisherID']);

    if ($input_values['callesi'] == 1) {
      unset($input_values['cns']);
    }
    unset($input_values['callesi']);

    // Ensure default value for twitter suffix.
    $input_values['twitter_suffix'] = (isset($input_values['twitter_suffix'])) ? $input_values['twitter_suffix'] : '';

    // Ensure default value for twitter handle.
    $input_values['twitter_handle'] = (isset($input_values['twitter_handle'])) ? $input_values['twitter_handle'] : '';

    // Ensure default value for twitter recommends.
    $input_values['twitter_recommends'] = (isset($input_values['twitter_recommends'])) ? $input_values['twitter_recommends'] : '';

    parent::validateForm($form, $form_state);

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $input_values = $form_state->getUserInput();
    $config = $this->config('sharethis.settings');
    // $form_state->getValues();
    // print '<pre>';print_r($form_state->getValues());print '</pre>';
    //  kint($input_values);
    // If the location is changing to/from 'content', clear the Field Info cache.
    $current_location = $config->get('location');
    $new_location = $input_values['location'];
    if (($current_location == 'content' || $new_location == 'content') && $current_location != $new_location) {
      $this->entityManager->clearCachedFieldDefinitions();
    }
    $config->set('button_option', $input_values['button_option'])
      ->set('service_option', $input_values['service_option'])
      ->set('option_extras', $input_values['option_extras'])
      ->set('callesi', $input_values['callesi'])
      ->set('location', $input_values['location'])
      ->set('node_types', $input_values['node_types'])
      ->set('comments', $input_values['comments'])
      ->set('weight', $input_values['weight'])
      ->set('publisherID', $input_values['publisherID'])
      ->set('late_load', $input_values['late_load'])
      ->set('twitter_suffix', $input_values['twitter_suffix'])
      ->set('twitter_handle', $input_values['twitter_handle'])
      ->set('twitter_recommends', $input_values['twitter_recommends'])
      ->set('option_onhover', $input_values['option_onhover'])
      ->set('option_neworzero', $input_values['option_neworzero'])
      ->set('option_shorten', $input_values['option_shorten'])
      ->set('cns.donotcopy', $input_values['cns']['donotcopy'])
      ->set('cns.hashaddress', $input_values['cns']['hashaddress'])
      ->save();
    parent::submitForm($form, $form_state);
  }

}
