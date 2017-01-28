<?php

namespace Drupal\Tests\facets\Unit\Plugin\widget;

use Drupal\facets\Result\Result;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for widget unit tests.
 */
abstract class WidgetTestBase extends UnitTestCase {

  /**
   * The widget to be tested.
   *
   * @var \Drupal\facets\Widget\WidgetPluginInterface
   */
  protected $widget;

  /**
   * An array containing the results for the widget.
   *
   * @var \Drupal\facets\Result\Result[]
   */
  protected $originalResults;

  /**
   * An array of possible query types.
   *
   * @var string[]
   */
  protected $queryTypes;

  /**
   * Sets up the container and other variables used in all the tests.
   */
  protected function setUp() {
    parent::setUp();

    /** @var \Drupal\facets\Result\Result[] $original_results */
    $original_results = [
      new Result('llama', 'Llama', 10),
      new Result('badger', 'Badger', 20),
      new Result('duck', 'Duck', 15),
      new Result('alpaca', 'Alpaca', 9),
    ];

    foreach ($original_results as $original_result) {
      $original_result->setUrl(new Url('test'));
    }
    $this->originalResults = $original_results;

    // Creates a mocked container, so we can access string translation.
    $container = $this->prophesize(ContainerInterface::class);
    $string_translation = $this->prophesize(TranslationInterface::class);
    $url_generator = $this->prophesize(UrlGeneratorInterface::class);
    $widget_manager = $this->prophesize(WidgetPluginManager::class);

    $container->get('plugin.manager.facets.widget')->willReturn($widget_manager->reveal());
    $container->get('string_translation')->willReturn($string_translation->reveal());
    $container->get('url_generator')->willReturn($url_generator->reveal());
    \Drupal::setContainer($container->reveal());

    $this->queryTypes = [
      'date' => 'date',
      'string' => 'string',
      'numeric' => 'numeric',
    ];
  }

  /**
   * Tests default configuration.
   */
  public function testDefaultConfiguration() {
    $default_config = $this->widget->defaultConfiguration();
    $this->assertEquals(['show_numbers' => FALSE, 'soft_limit' => 0], $default_config);
  }

  /**
   * Tests get query type.
   */
  public function testGetQueryType() {
    $result = $this->widget->getQueryType($this->queryTypes);
    $this->assertEquals('string', $result);
  }

  /**
   * Build a formattable markup object to use as assertion.
   *
   * @param string $text
   *   Text to display.
   * @param int $count
   *   Number of results.
   * @param bool $active
   *   Link is active.
   * @param bool $show_numbers
   *   Numbers are displayed.
   *
   * @return array
   *   A render array.
   */
  protected function buildLinkAssertion($text, $count = 0, $active = FALSE, $show_numbers = TRUE) {
    return [
      '#theme' => 'facets_result_item',
      '#value' => $text,
      '#show_count' => $show_numbers && ($count !== NULL),
      '#count' => $count,
      '#is_active' => $active,
    ];
  }

}
