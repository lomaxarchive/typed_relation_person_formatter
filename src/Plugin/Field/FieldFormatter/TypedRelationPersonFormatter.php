<?php

namespace Drupal\typed_relation_person_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\taxonomy\Entity\Term;

/**
 * Plugin implementation of the 'typed_relation_person' formatter.
 *
 * @FieldFormatter(
 *   id = "typed_relation_person",
 *   label = @Translation("Typed Relation Person Formatter"),
 *   field_types = {
 *     "typed_relation"
 *   }
 * )
 */
class TypedRelationPersonFormatter extends EntityReferenceLabelFormatter
{
  /**
   * {@inheritdoc}
   */
  public static function defaultSettings()
  {
    return [
      "show_relator" => true,
      "link" => true,
      "relator_options" => [],
    ] + parent::defaultSettings();
  }

  /**
   * Get relator options from the field configuration.
   */
  protected function getRelatorOptions()
  {
    static $options = null;

    if ($options !== null) {
      return $options;
    }

    $options = [];

    $field_config = \Drupal::entityTypeManager()
      ->getStorage("field_config")
      ->load("node.islandora_object.field_linked_agent");

    if ($field_config) {
      $settings = $field_config->getSettings();
      if (!empty($settings["rel_types"])) {
        $options = $settings["rel_types"];
      }
    }

    return $options;
  }

  /**
   * Build shortcode → relator map dynamically.
   */
  protected function buildShortcodeMap()
  {
    $relators = $this->getRelatorOptions();
    $map = [];

    foreach ($relators as $key => $label) {
      if (strpos($key, ":") !== false) {
        list($scheme, $code) = explode(":", $key, 2);
        $map[strtolower($code)] = $key;
      } elseif (preg_match("/\((.*?)\)/", $label, $matches)) {
        $map[strtolower($matches[1])] = $key;
      }
    }

    return $map;
  }

  /**
   * Convert shortcode to relator key.
   */
  protected function mapShortcodeToRelator($shortcode, $name = "")
  {
    if (empty($shortcode)) {
      return "relators:prf";
    }

    $map = $this->buildShortcodeMap();
    $shortcode = strtolower(trim($shortcode));

    if (!isset($map[$shortcode])) {
      \Drupal::logger("typed_relation_person_formatter")->warning(
        'Unrecognized shortcode "@code" for "@name". Defaulting to Performer.',
        [
          "@code" => $shortcode,
          "@name" => $name,
        ]
      );

      return "relators:prf";
    }

    return $map[$shortcode];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state)
  {
    $form = parent::settingsForm($form, $form_state);

    // Attach CSS library.
    $form["#attached"]["library"][] = "typed_relation_person_formatter/relator_form";

    $form["show_relator"] = [
      "#type" => "checkbox",
      "#title" => $this->t("Show relationship type (relator)"),
      "#default_value" => $this->getSetting("show_relator"),
    ];

    $relator_options = $this->getRelatorOptions();

    $form["relator_options"] = [
      "#type" => "checkboxes",
      "#title" => $this->t("Allowed relators"),
      "#options" => $relator_options,
      "#default_value" => array_keys(array_filter($this->getSetting("relator_options"))),
      "#description" => $this->t("Only these relators will be displayed. Leave unchecked to show all."),
      "#attributes" => [
        "class" => ["relator-options-col"],
      ],
    ];

    return $form;
  }
  /**
   * {@inheritdoc}
   */
  public function settingsSummary()
  {
    $summary = [];

    $summary[] = $this->getSetting("show_relator") ? $this->t("Relator shown") : $this->t("Relator hidden");

    $selected = array_filter($this->getSetting("relator_options"));

    if (!empty($selected)) {
      $summary[] = $this->t("Allowed relators: @list", [
        "@list" => implode(", ", $selected),
      ]);
    } else {
      $summary[] = $this->t("Allowed relators: All");
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode)
  {
    $elements = [];

    $target_ids = array_filter(array_map(fn($i) => $i->target_id ?? null, iterator_to_array($items)));
    $terms = !empty($target_ids) ? Term::loadMultiple($target_ids) : [];

    $selected_relators = array_filter($this->getSetting("relator_options"));
    $filtering = !empty($selected_relators);

    $all_relators = $this->getRelatorOptions();

    foreach ($items as $delta => $item) {
      if (empty($item->target_id) || !isset($terms[$item->target_id])) {
        continue;
      }

      if ($filtering && !empty($item->rel_type) && !isset($selected_relators[$item->rel_type])) {
        continue;
      }

      $term = $terms[$item->target_id];

      $preferred_name = $term->label();

      if ($term->hasField("field_person_preferred_name") && !$term->get("field_person_preferred_name")->isEmpty()) {
        $name_item = $term->get("field_person_preferred_name")->first();
        $components = $name_item->toArray();
        $preferred_name = trim(($components["given"] ?? "") . " " . ($components["family"] ?? ""));
      }

      $relator_text = "";

      if ($this->getSetting("show_relator")) {
        $relator = $item->rel_type ?: "relators:prf";
        $relator_label = $all_relators[$relator] ?? $relator;

        $relator_text = " (" . $relator_label . ")";
      }

      if ($this->getSetting("link")) {
        $elements[$delta] = [
          "#type" => "inline_template",
          "#template" => '<a href="{{ url }}">{{ name }}</a>{{ relator }}',
          "#context" => [
            "name" => $preferred_name,
            "relator" => $relator_text,
            "url" => $term->toUrl()->toString(),
          ],
        ];
      } else {
        $elements[$delta] = [
          "#markup" => $preferred_name . $relator_text,
        ];
      }

      $elements[$delta]["#cache"]["tags"] = $term->getCacheTags();
    }

    return $elements;
  }
}
