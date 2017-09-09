<?php

namespace Drupal\facets\QueryType;

use Drupal\facets\Result\Result;

/**
 * A base class for query type plugins adding range.
 */
abstract class QueryTypeRangeBase extends QueryTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $query = $this->query;

    // Alter the query here.
    if (!empty($query)) {
      $options = &$query->getOptions();

      $operator = $this->facet->getQueryOperator();
      $field_identifier = $this->facet->getFieldIdentifier();
      $exclude = $this->facet->getExclude();
      $options['search_api_facets'][$field_identifier] = [
        'field' => $field_identifier,
        'limit' => $this->facet->getHardLimit(),
        'operator' => $this->facet->getQueryOperator(),
        'min_count' => $this->facet->getMinCount(),
        'missing' => FALSE,
      ];

      // Add the filter to the query if there are active values.
      $active_items = $this->facet->getActiveItems();
      $filter = $query->createConditionGroup($operator, ['facet:' . $field_identifier]);
      if (count($active_items)) {
        foreach ($active_items as $value) {
          $range = $this->calculateRange($value);

          $item_filter = $query->createConditionGroup($exclude ? 'OR' : 'AND', ['facet:' . $field_identifier]);
          $item_filter->addCondition($this->facet->getFieldIdentifier(), $range['start'], $exclude ? '<' : '>=');
          $item_filter->addCondition($this->facet->getFieldIdentifier(), $range['stop'], $exclude ? '>' : '<=');

          $filter->addConditionGroup($item_filter);
        }
        $query->addConditionGroup($filter);
      }
    }
  }

  /**
   * Calculate the range for a given facet filter value.
   *
   * Used when adding active items in self::execute() to $this->query to include
   * the range conditions for the value.
   *
   * @param string $value
   *   The raw value for the facet filter.
   *
   * @return array
   *   Keyed with 'start' and 'stop' values.
   */
  abstract public function calculateRange($value);

  /**
   * {@inheritdoc}
   */
  public function build() {
    $query_operator = $this->facet->getQueryOperator();

    // Go through the results and add facet results grouped by filters
    // defined by self::calculateResultFilter().
    if (!empty($this->results)) {
      $facet_results = [];
      foreach ($this->results as $result) {
        if ($result['count'] || $query_operator == 'or') {
          $count = $result['count'];
          $result_filter = $this->calculateResultFilter(trim($result['filter'], '"'));
          if (isset($facet_results[$result_filter['raw']])) {
            $facet_results[$result_filter['raw']]->setCount(
              $facet_results[$result_filter['raw']]->getCount() + $count
            );
          }
          else {
            $facet_results[$result_filter['raw']] = new Result($result_filter['raw'], $result_filter['display'], $count);
          }
        }
      }

      $this->facet->setResults($facet_results);
    }
    return $this->facet;
  }

  /**
   * Calculate the grouped facet filter for a given value.
   *
   * @param string $value
   *   The raw value for the facet before grouping.
   *
   * @return array
   *   Keyed by 'display' value to be shown to the user, and 'raw' to be used
   *   for the url.
   */
  abstract public function calculateResultFilter($value);

}
