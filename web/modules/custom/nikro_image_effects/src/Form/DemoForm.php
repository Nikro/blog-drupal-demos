<?php

namespace Drupal\nikro_image_effects\Form;

use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\Image;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\ImageEffectManager;
use Drupal\image_effects\Plugin\ImageEffectsPluginManager;
use Drupal\media\Entity\Media;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class DemoForm.
 */
class DemoForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'nikro_image_effects_demo_form';
  }

  /**
   * Module Handler that we need to get paths and stuff.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Image effect manager.
   *
   * @var \Drupal\image\ImageEffectManager
   */
  protected $imageEffectManager;

  /**
   * Image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * The overridden default constructor.
   */
  public function __construct(ModuleHandler $moduleHandler, ImageEffectManager $image_effect_manager, ImageFactory $image_factory) {
    $this->moduleHandler = $moduleHandler;
    $this->imageEffectManager = $image_effect_manager;
    $this->imageFactory = $image_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('plugin.manager.image.effect'),
      $container->get('image.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['layout'] = [
      '#type' => 'select',
      '#title' => $this->t('Image Layout'),
      '#description' => $this->t('Two versions are available so far.'),
      '#options' => ['fb' => $this->t('Facebook'), 'tw' => $this->t('Twitter')],
      '#default_value' => $form_state->hasValue('layout') ? $form_state->getValue('layout') : '',
    ];
    $form['builder'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Layout details'),
    ];

    // If the final preview is ready, show it now.
    if ($form_state->has('preview_uri')) {
      $form['builder']['preview']['#prefix'] = '<em>' . $this->t('Layout Preview') . '</em> - ' . Link::fromTextAndUrl('See final preview here', Url::fromUri('internal:#final-preview'))->toString() . '<br/>';
      $form['builder']['preview_final'] = [
        '#theme' => 'image',
        '#uri' => $form_state->get('preview_uri'),
        '#alt' => $this->t('Final preview'),
        '#title' => $this->t('Final preview'),
        '#height' => 'auto',
        '#prefix' => '<em id="final-preview">' . $this->t('Final Preview') . '</em><br/>',
        '#suffix' => '<br/>' . $this->t('If you want to adjust the teaser, feel free to change the values and hit Rebuild!'),
        '#width' => '800x',
        '#weight' => 100
      ];
    }

    $form['builder']['image'] = [
      '#type' => 'media_library',
      '#allowed_bundles' => ['image'],
      '#required' => TRUE,
      '#title' => $this->t('Image'),
      '#description' => $this->t('Upload or select the image you want to use.'),
      '#cardinality' => 1,
      '#default_value' => $form_state->hasValue('image') ? $form_state->getValue('image') : '',
    ];
    $form['builder']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Headline'),
      '#description' => $this->t('Just some random headline to be overlayed on the image.'),
      '#default_value' => $form_state->hasValue('title') ? $form_state->getValue('title') : '',
    ];
    $form['builder']['color_txt'] = [
      '#type' => 'colored_box',
      '#title' => $this->t('Text Color'),
      '#color_options' => ['#1abc9c', '#95a5a6', '#3498db', '#34495e', '#f1c40f', '#e74c3c', '#c0392b', '#000000', '#ffffff'],
      '#required' => TRUE,
      '#tree' => TRUE,
      '#default_value' => [
        'color' => $form_state->hasValue(['color_txt', 'color']) ?
          $form_state->getValue(['color_txt', 'color']) : '#c0392b',
      ],
      '#weight' => 15
    ];
    $form['builder']['color_bg'] = [
      '#type' => 'colored_box',
      '#title' => $this->t('Background Color'),
      '#color_options' => ['#1abc9c', '#95a5a6', '#3498db', '#34495e', '#f1c40f', '#e74c3c', '#c0392b', '#000000', '#ffffff'],
      '#required' => TRUE,
      '#tree' => TRUE,
      '#default_value' => [
        'color' => $form_state->hasValue(['color_bg', 'color']) ?
          $form_state->getValue(['color_bg', 'color']) : '#3498db',
      ],
      '#weight' => 16
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Rebuild Image'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $media_id = $form_state->getValue('image');
    $media = Media::load($media_id);
    $source_uri = $media->field_media_image->entity->getFileUri();

    // If we have the layout and all the data right, let's request the preview.
    if ($layout = $form_state->getValue('layout')) {
      $preview_uri = $this->buildImage($source_uri, $form_state->getValue('layout'), $form_state);
      $form_state->set('preview_uri', $preview_uri);
    }

    $form_state->setRebuild(TRUE);
  }

  /**
   * Generates the teaser itself.
   *
   * @param string $source_uri
   *   URI as string for source.
   * @param string $layout
   *   Layout as string.
   * @param \Drupal\Core\Form\FormState $form_state
   *   Form's formstate.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function buildImage(string $source_uri, string $layout, FormState $form_state) {
    // Get the base-style ready - currently same for all layouts.
    $image_style = $layout === 'fb' ? ImageStyle::load('facebook_post') : ImageStyle::load('twitter_post');
    $derivative_uri = $image_style->buildUri($source_uri);
    $fonts_path = $this->moduleHandler->getModule('nikro_image_effects')->getPath() . '/assets/fonts';

    // Create a temporary derivative image.
    if (!$image_style->createDerivative($source_uri, $derivative_uri)) {
      \Drupal::messenger()->addError('Something went wrong while creating the derivative image.');
    }
    $image = $this->imageFactory->get($derivative_uri);

    // Default values - potentially have to be offered a GUI controller later.
    $background_color = $form_state->hasValue('color_bg') ? $form_state->getValue(['color_bg', 'settings', 'color']) : '#3498db';

    // Custom variables, but common to all formats.
    $text_color = $form_state->hasValue('color_txt') ? $form_state->getValue(['color_txt', 'settings', 'color']) : '#c0392b';

    switch ($layout) {
      case 'fb':
        // Text overlay.
        $title = $form_state->hasValue('title') ? $form_state->getValue('title') : $this->t('Headline');
        $this->overlayText($image, $title, [
          'font_uri' => $fonts_path . '/OpenSans/OpenSans-Regular.ttf',
          'font_size' => '60',
          'color' => $text_color,
          'background' => $background_color,
          'x' => '0',
          'y' => '673'
        ]);
        break;

      case 'tw':
        $title = $form_state->hasValue('title') ? $form_state->getValue('title') : $this->t('Headline');
        $this->overlayText($image, $title, [
          'font_uri' => $fonts_path . '/OpenSans_Condensed/OpenSans_Condensed-Bold.ttf',
          'font_size' => '60',
          'color' => $text_color,
          'background' => $background_color,
          'x' => '0',
          'y' => '562'
        ]);
        break;
    }

    // All images in the end have the arrow overlay, we'll use the water-mark method.
    $watermark_options = [
      'data' => [
        'watermark_image' => $this->moduleHandler->getModule('nikro_image_effects')->getPath() . '/assets/images/n.png',
        'watermark_width' => 100,
        'watermark_height' => 100,
        'placement' => 'right-top',
        'x_offset' => -20,
        'y_offset' => 20,
        'opacity' => 100,
      ]
    ];
    $watermark_effect = $this->imageEffectManager->createInstance('image_effects_watermark', $watermark_options);
    $watermark_effect->applyEffect($image);

    // Save all our effects into the same derivative image.
    $image->save($derivative_uri);

    return $derivative_uri;
  }

  /**
   * Overlays the text, using image effect plugins.
   *
   * @param \Drupal\Core\Image\Image $image
   *   The image we draw on.
   * @param string $string
   *   The text string.
   * @param array $details
   *   Details - array overriding defaults.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  private function overlayText(Image $image, string $string, array $details) {
    $text_overlay_options = [
      'data' => [
        'font'          => [
          'name'                  => 'Open Sans',
          'uri'                   => $details['font_uri'],
          'size'                  => $details['font_size'],
          'angle'                 => 0,
          'color'                 => $details['color'],
          'stroke_mode'           => FALSE,
          'stroke_color'          => '#000000FF',
          'outline_top'           => 0,
          'outline_right'         => 0,
          'outline_bottom'        => 0,
          'outline_left'          => 0,
          'shadow_x_offset'       => 1,
          'shadow_y_offset'       => 1,
          'shadow_width'          => 0,
          'shadow_height'         => 0,
        ],
        'layout'       => [
          'padding_top'           => 10,
          'padding_right'         => 1200,
          'padding_bottom'        => 10,
          'padding_left'          => 20,
          'x_offset'              => 0,
          'y_offset'              => 0,
          'background_color'      => $details['background'] . 'C8',
          'overflow_action'       => 'crop',
          'extended_color'        => $details['background'] . 'C8',
          'x_pos'                 => $details['x'],
          'y_pos'                 => $details['y'],
        ],
        'text_string'             => $string,
      ]
    ];
    /** @var \Drupal\image_effects\Plugin\ImageEffect\TextOverlayImageEffect $text_overlay */
    $text_overlay = $this->imageEffectManager->createInstance('image_effects_text_overlay', $text_overlay_options);
    $text_overlay->applyEffect($image);
  }

}
