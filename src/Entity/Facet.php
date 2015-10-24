<?php

/**
 * @file
 * Contains \Drupal\facetapi\Entity\Facet.
 */

namespace Drupal\facetapi\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\facetapi\FacetInterface;
use Drupal\facetapi\Result\Result;

/**
 * Defines the search index configuration entity.
 *
 * @ConfigEntityType(
 *   id = "facetapi_facet",
 *   label = @Translation("Facet"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigEntityStorage",
 *     "list_builder" = "Drupal\facetapi\FacetListBuilder",
 *     "form" = {
 *       "default" = "Drupal\facetapi\Form\FacetForm",
 *       "edit" = "Drupal\facetapi\Form\FacetForm",
 *       "display" = "Drupal\facetapi\Form\FacetDisplayForm",
 *       "delete" = "Drupal\facetapi\Form\FacetDeleteConfirmForm",
 *     },
 *   },
 *   admin_permission = "administer facetapi",
 *   config_prefix = "facet",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "status" = "status"
 *   },
 *   config_export = {
 *     "id",
 *     "name",
 *     "field_identifier",
 *     "query_type_name",
 *     "facet_source_id",
 *     "widget",
 *     "widget_configs",
 *     "processor_configs",
 *     "empty_behavior",
 *     "empty_behavior_configs",
 *     "only_visible_when_facet_source_is_visible",
 *   },
 *   links = {
 *     "canonical" = "/admin/config/search/facet-api",
 *     "add-form" = "/admin/config/search/facet-api/add-facet",
 *     "edit-form" = "/admin/config/search/facet-api/{facetapi_facet}/edit",
 *     "delete-form" = "/admin/config/search/facet-api/{facetapi_facet}/delete",
 *   }
 * )
 */
class Facet extends ConfigEntityBase implements FacetInterface {

  /**
   * The ID of the index.
   *
   * @var string
   */
  protected $id;

  /**
   * A name to be displayed for the index.
   *
   * @var string
   */
  protected $name;

  /**
   * A string describing the index.
   *
   * @var string
   */
  protected $description;

  /**
   * A string describing the widget.
   *
   * @var string
   */
  protected $widget;

  /**
   * Configuration for the widget. This is a key-value stored array.
   *
   * @var string
   */
  protected $widget_configs;

  /**
   * Configuration for the empty behavior.
   *
   * @var string
   */
  protected $empty_behavior;

  /**
   * An array of options configuring this index.
   *
   * @var array
   *
   * @see getOptions()
   */
  protected $options = array();

  /**
   * The field identifier.
   *
   * @var string
   */
  protected $field_identifier;

  /**
   * The query type name.
   *
   * @var string
   */
  protected $query_type_name;

  /**
   * The plugin name of the url processor.
   *
   * @var string
   */
  protected $url_processor_name;

  /**
   * The id of the facet source.
   *
   * @var string
   */
  protected $facet_source_id;

  /**
   * The path all the links should point to.
   *
   * @var string
   */
  protected $path;

  /**
   * The results.
   *
   * @var Result[]
   */
  protected $results = [];

  protected $active_values = [];

  /**
   * An array containing the facet source plugins.
   *
   * @var array
   */
  protected $facetSourcePlugins;

  /**
   * An array containing all processors and their configuration.
   *
   * @var array
   */
  protected $processor_configs;

  /**
   * Cached information about the processors available for this facet.
   *
   * @var \Drupal\facetapi\Processor\ProcessorInterface[]|null
   *
   * @see loadProcessors()
   */
  protected $processors;

  /**
   * A boolean that defines whether or not the facet is only visible when the
   * facet source is visible.
   *
   * @var boolean
   */
  protected $only_visible_when_facet_source_is_visible;

  /**
   * Widget Plugin Manager
   *
   * @var object
   */
  protected $widget_plugin_manager;

  /**
   * Query Type Plugin Manager
   *
   * @var object
   */
  protected $query_type_manager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);

    $container = \Drupal::getContainer();
    /** @var \Drupal\facetapi\Widget\WidgetPluginManager $widget_plugin_manager */
    $this->widget_plugin_manager = $container->get('plugin.manager.facetapi.widget');
    /** @var \Drupal\facetapi\QueryType\QueryTypePluginManager $query_type_manager */
    $this->query_type_manager = $container->get('plugin.manager.facetapi.query_type');

  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function setWidget($widget) {
    $this->widget = $widget;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWidget() {
    return $this->widget;
  }


  /**
   * {@inheritdoc}
   */
  public function getQueryType() {
    $facet_source = $this->getFacetSource();
    $query_types = $facet_source->getQueryTypesForFacet($this);

    // Get our widget configured for this facet.
    /** @var \Drupal\facetapi\Widget\WidgetInterface $widget */
    $widget = $this->widget_plugin_manager->createInstance($this->getWidget());
    // Give the widget the chance to select a preferred query type. This is
    // useful with a date widget, as it needs to select the date query type.
    return $widget->getQueryType($query_types);
  }

  /**
   * Get the field alias used to identify the facet in the url.
   *
   * @return mixed
   */
  public function getFieldAlias() {
    // For now, create the field alias based on the field identifier.
    $field_alias = preg_replace('/[:\/]+/', '_', $this->field_identifier);
    return $field_alias;
  }

  /**
   * Sets an item with value to active.
   *
   * @param $value
   */
  public function setActiveItem($value) {
    if (!in_array($value, $this->active_values)) {
      $this->active_values[] = $value;
    }
  }

  /**
   * Get all the active items in the facet.
   *
   * @return mixed
   */
  public function getActiveItems() {
    return $this->active_values;
  }

  /**
   * {@inheritdoc}
   */
  public function getOption($name, $default = NULL) {
    return isset($this->options[$name]) ? $this->options[$name] : $default;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions() {
    return $this->options;
  }

  /**
   * {@inheritdoc}
   */
  public function setOption($name, $option) {
    $this->options[$name] = $option;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  public function getFieldIdentifier() {
    return $this->field_identifier;
  }

  public function setFieldIdentifier($field_identifier) {
    $this->field_identifier = $field_identifier;
    return $this;
  }

  public function getQueryTypes() {
    return $this->query_type_name;
  }

  public function setFieldEmptyBehavior($behavior_id) {
    $this->empty_behavior = $behavior_id;
    return $this;
  }

  public function getFieldEmptyBehavior() {
    return $this->empty_behavior;
  }

  public function getUrlProcessorName() {
    // @Todo: for now if the url processor is not set, defualt to query_string.
    return isset($this->url_processor_name) ? $this->url_processor_name : 'query_string';
  }

  public function getName() {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function setFacetSourceId($facet_source_id) {
    $this->facet_source_id = $facet_source_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFacetSource() {

    /** @var $facet_source_plugin_manager \Drupal\facetapi\FacetSource\FacetSourcePluginManager */
    $facet_source_plugin_manager = \Drupal::service('plugin.manager.facetapi.facet_source');

    return $facet_source_plugin_manager->createInstance($this->facet_source_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getFacetSourceId() {
    return $this->facet_source_id;
  }

  /**
   * Retrieves all processors supported by this facet.
   *
   * @return \Drupal\facetapi\Processor\ProcessorInterface[]
   *   The loaded processors, keyed by processor ID.
   */
  protected function loadProcessors() {
    if (!isset($this->processors)) {
      /** @var $processor_plugin_manager \Drupal\facetapi\Processor\ProcessorPluginManager */
      $processor_plugin_manager = \Drupal::service('plugin.manager.facetapi.processor');
      $processor_settings = $this->getOption('processors', array());

      foreach ($processor_plugin_manager->getDefinitions() as $name => $processor_definition) {
        if (class_exists($processor_definition['class']) && empty($this->processors[$name])) {
          // Create our settings for this processor.
          $settings = empty($processor_settings[$name]['settings']) ? array() : $processor_settings[$name]['settings'];
          $settings['facet'] = $this;

          /** @var $processor \Drupal\facetapi\Processor\ProcessorInterface */
          $processor = $processor_plugin_manager->createInstance($name, $settings);
          $this->processors[$name] = $processor;
        }
        elseif (!class_exists($processor_definition['class'])) {
          \Drupal::logger('facetapi')->warning('Processor @id specifies a non-existing @class.', array('@id' => $name, '@class' => $processor_definition['class']));
        }
      }
    }

    return $this->processors;
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $parameters = parent::urlRouteParameters($rel);
    return $parameters;
  }

  public function getResults() {
    return $this->results;
  }

  /**
   * Set an array of Result objects.
   *
   * @param array $results
   *   Array containing \Drupal\facetapi\Result\Result objects.
   */
  public function setResults(array $results) {
    $this->results = $results;
    // If there are active values,
    // set the results which are active to active.
    if (count($this->active_values)) {
      foreach ($this->results as $result) {
        if (in_array($result->getRawValue(), $this->active_values)) {
          $result->setActiveState(TRUE);
        }
      }
    }
  }

  /**
   * Until facet api supports more than just search api, this is enough.
   *
   * @return string
   */
  public function getManagerPluginId() {
    return 'facetapi_default';
  }

  /**
   * @inheritdoc
   */
  public function isActiveValue($value) {
    $is_active = FALSE;
    if (in_array($value, $this->active_values)) {
      $is_active = TRUE;
    }
    return $is_active;
  }

  /**
   * {@inheritdoc}
   */
  public function getFacetSources($only_enabled = false) {
    if (!isset($this->facetSourcePlugins)) {
      $this->facetSourcePlugins = [];

      /** @var $facet_source_plugin_manager \Drupal\facetapi\FacetSource\FacetSourcePluginManager */
      $facet_source_plugin_manager = \Drupal::service('plugin.manager.facetapi.facet_source');

      foreach ($facet_source_plugin_manager->getDefinitions() as $name => $facet_source_definition) {
        if (class_exists($facet_source_definition['class']) && empty($this->facetSourcePlugins[$name])) {
          // Create our settings for this facet source..
          $config = isset($this->facetSourcePlugins[$name]) ? $this->facetSourcePlugins[$name] : [];

          /** @var $facet_source \Drupal\facetapi\FacetSource\FacetSourceInterface */
          $facet_source = $facet_source_plugin_manager->createInstance($name, $config);
          $this->facetSourcePlugins[$name] = $facet_source;
        }
        elseif (!class_exists($facet_source_definition['class'])) {
          \Drupal::logger('facetapi')->warning('Facet Source @id specifies a non-existing @class.', ['@id' => $name, '@class' => $facet_source_definition['class']]);
        }
      }
    }

    // Filter datasources by status if required.
    if (!$only_enabled) {
      return $this->facetSourcePlugins;
    }

    return array_intersect_key($this->facetSourcePlugins, array_flip($this->facetSourcePlugins));
  }

  public function setPath($path) {
    $this->path = $path;
  }

  public function getPath() {
    return $this->path;
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessorConfigs() {
    return $this->processor_configs;
  }
  /**
   * {@inheritdoc}
   */
  public function setProcessorConfigs($processor_config = []) {
    $this->processor_configs = $processor_config;
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessors($only_enabled = TRUE) {
    $processors = $this->loadProcessors();

    // Filter processors by status if required. Enabled processors are those
    // which have settings in the "processors" option.
    if ($only_enabled) {
      $processors_settings = $this->getOption('processors', array());
      $processors = array_intersect_key($processors, $processors_settings);
    }

    return $processors;
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessorsByStage($stage, $only_enabled = TRUE) {
    $processors = $this->loadProcessors();
    $processor_settings = $this->getOption('processors', array());
    $processor_weights = array();

    // Get a list of all processors meeting the criteria (stage and, optionally,
    // enabled) along with their effective weights (user-set or default).
    foreach ($processors as $name => $processor) {
      if ($processor->supportsStage($stage) && !($only_enabled && empty($processor_settings[$name]))) {
        if (!empty($processor_settings[$name]['weights'][$stage])) {
          $processor_weights[$name] = $processor_settings[$name]['weights'][$stage];
        }
        else {
          $processor_weights[$name] = $processor->getDefaultWeight($stage);
        }
      }
    }

    // Sort requested processors by weight.
    asort($processor_weights);

    $return_processors = array();
    foreach ($processor_weights as $name => $weight) {
      $return_processors[$name] = $processors[$name];
    }
    return $return_processors;
  }

  /**
   * {@inheritdoc}
   */
  public function setOnlyVisibleWhenFacetSourceIsVisible($only_visible_when_facet_source_is_visible) {
    $this->only_visible_when_facet_source_is_visible = $only_visible_when_facet_source_is_visible;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOnlyVisibleWhenFacetSourceIsVisible() {
    return $this->only_visible_when_facet_source_is_visible;
  }

}
