<?php

/**
 * @file
 * Contains Drupal\tagadelic\TagadelicTagCloud.
 */

namespace Drupal\tagadelic;

class TagadelicTagCloud {

  /**
   * An identifier for this cloud. Must be unique.
   *
   * @var int|string
   */
  protected $id = NULL;

  /**
   * List of the tags in this cloud.
   *
   * @var array
   */
  protected $tags = array();

  /**
   * Amount of steps to weight the cloud in. Defaults to 6.
   *
   * @var int
   */
  protected $steps = 6;

  /**
   * Do the tag weights need to recalculated with $this->calculateTagWeights()?
   *
   * @var bool
   */
  protected $needsRecalc = TRUE;

  /**
   * An instance of TagadelicDrupalWrapper. Used primarily for testing purposes.
   *
   * @var TagadelicDrupalWrapper
   */
  protected $drupalWrapper;

  /**
   * Initialize a new instance of TagadelicTagCloud.
   *
   * @param int $id
   *   Integer, identifies this cloud; used for caching and re-fetching of
   *   previously built clouds.
   *
   * @param array $tags
   *   Provide tags on building. Tags can be added later, using $this->addTag().
   *
   * @return TagadelicTagCloud.
   */
  public function __construct($id, $tags = array()) {
    $this->id = $id;
    $this->tags = $tags;
  }

  /**
   * Gets the id of the current TagadelicTagCloud instance.
   *
   * @ingroup getters
   * @returns mixed
   *   Id of the current TagadelicTagCloud instance.
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Gets the tags on the current instance.
   *
   * @ingroup getters
   * @returns array
   *   An array TagadelicTag objects.
   */
  public function getTags() {
    $this->calculateTagWeights();
    return $this->tags;
  }

  /**
   * Adds a TagadelicTag object to the current TagadelicTagCloud.
   *
   * @param TagadelicTag $tag
   *   An instance of TagadelicTag.
   *
   * @return TagadelicTagCloud
   *   The current instance, for chaining.
   */
  public function addTag($tag) {
    $this->tags[] = $tag;
    return $this;
  }

  /**
   * Sets $this->drupalWrapper to an instance of TagadelicDrupalWrapper.
   *
   * @param TagadelicDrupalWrapper $wrapper
   *   A mock Drupal instance to use for testing.
   *
   * @return TagadelicTagCloud
   *   The current instance of Drupal\tagadelic\TagadelicTagCloud.
   */
  public function setDrupalWrapper($wrapper) {
    $this->drupalWrapper = $wrapper;
    return $this;
  }

  /**
   * Get an instance of TagadelicDrupalWrapper.
   *
   * @return TagadelicDrupalWrapper
   *   Value in $this->drupal.
   */
  public function getDrupalWrapper() {
    if (empty($this->drupalWrapper)) {
      $this->setDrupalWrapper(new TagadelicDrupalWrapper());
    }
    return $this->drupalWrapper;
  }

  /**
   * Instantiate an instance of TagadelicTagCloud from the cache.
   *
   * @param int $id
   *   The id of the TagadelicTagCloud instance to retrieve from the cache.
   * @param Object $drupal
   *   The current Drupal instance.
   *
   * @return TagadelicTagCloud
   *   A new instance from the cache.
   */
  public static function fromCache($id, $drupal) {
    $cache_id = "tagadelic_cloud_{$id}";
    return $drupal->cache_get($cache_id);
  }

  /**
   * Writes the cloud to cache. Will calculateTagWeights if needed.
   *
   * @return TagadelicTagCloud
   *   The current instance.
   */
  public function toCache() {
    $cache_id = "tagadelic_cloud_{$this->id}";
    $this->getDrupalWrapper()->cache_set($cache_id, $this);
    return $this;
  }

  /**
   * Sorts the tags by a given property.
   *
   * @param string $property
   *   The property to sort the tags on.
   *
   * @return TagadelicTagCloud
   *   The current instance of TagadelicTagCloud.
   */
  public function sortTagsBy($property) {
    if ($property == "random") {
      $this->getDrupalWrapper()->shuffle($this->tags);
    }
    else {
      // PHP Bug: https://bugs.php.net/bug.php?id=50688 - Supress the error.
      @usort($this->tags, array($this, "sortBy{$property}"));
    }
    return $this;
  }

  /**
   * Calculates and sets the weight of each TagadelicTag in current instance.
   *
   * @return TagadelicTagCloud
   *   The current instance of TagadelicTagCloud, for chaining.
   */
  public function calculateTagWeights() {
    $tags = array();
    // Find minimum and maximum log-count.
    $min = 1e9;
    $max = -1e9;
    foreach ($this->tags as $id => $tag) {
      $min = min($min, $tag->distributed());
      $max = max($max, $tag->distributed());
      $tags[$id] = $tag;
    }
    // Note: we need to ensure the range is slightly too large to make sure even
    // the largest element is rounded down.
    $range = max(.01, $max - $min) * 1.0001;
    foreach ($tags as $id => $tag) {
      $this->tags[$id]->setWeight(1 + floor($this->steps * ($tag->distributed() - $min) / $range));
    }
    return $this;
  }

  /**
   * Sort by name.
   *
   * @param string $a
   *   A string.
   * @param string $b
   *   Another string.
   *
   * @return int
   *   <0 if $a is less than $b, >0 if $b is less than $a, 1 if they are equal.
   */
  protected function sortByName($a, $b) {
    return strcoll($a->get_name(), $b->get_name());
  }

  /**
   * Sort by count.
   *
   * @param int $a
   *   An integer.
   * @param int $b
   *   Another integer.
   *
   * @return int
   *   <0 if $a is less than $b, >0 if $b is less than $a, 1 if they are equal.
   */
  protected function sortByCount($a, $b) {
    $ac = $a->get_count();
    $bc = $b->get_count();
    if ($ac == $bc) {
      return 0;
    }
    // Highest first, High to low.
    return ($ac < $bc) ? 1 : -1;
  }
}
