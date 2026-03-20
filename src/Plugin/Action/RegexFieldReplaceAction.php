<?php

declare(strict_types=1);

namespace Drupal\regex_field_replace\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Applies a regex find/replace to a specified field on each entity.
 */
#[Action(
  id: 'regex_field_replace',
  label: new TranslatableMarkup('Regex field replace'),
  type: '',   // Empty = applies to all entity types.
)]
class RegexFieldReplaceAction extends ActionBase {

  // ---------------------------------------------------------------------------
  // Access
  // ---------------------------------------------------------------------------

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\Core\Entity\EntityInterface $object */
    $result = $object->access('update', $account, TRUE);
    return $return_as_object ? $result : $result->isAllowed();
  }

  // ---------------------------------------------------------------------------
  // Configuration form (shown by VBO before execution)
  // ---------------------------------------------------------------------------

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['field_name'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Field name'),
      '#description'   => $this->t(
        'Machine name of the field to update (e.g. <code>title</code>, <code>field_my_text</code>).'
      ),
      '#required'      => TRUE,
      '#default_value' => $this->configuration['field_name'] ?? '',
    ];

    $form['pattern'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Regex pattern'),
      '#description'   => $this->t(
        'A PHP <code>preg_replace()</code>-compatible pattern, <em>including delimiters</em> '
        . '(e.g. <code>/^(\w+)-(\w+)$/</code>).'
      ),
      '#required'      => TRUE,
      '#default_value' => $this->configuration['pattern'] ?? '',
    ];

    $form['replacement'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Replacement'),
      '#description'   => $this->t(
        'Replacement string. Back-references use <code>$1</code>, <code>$2</code>, … notation '
        . '(e.g. <code>$2,$1</code>).'
      ),
      '#required'      => FALSE,
      '#default_value' => $this->configuration['replacement'] ?? '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $pattern = trim($form_state->getValue('pattern'));

    // Suppress PHP warnings and treat a falsy return as invalid.
    set_error_handler(static function () {}, E_WARNING);
    $valid = preg_match($pattern, '') !== FALSE;
    restore_error_handler();

    if (!$valid) {
      $form_state->setErrorByName(
        'pattern',
        $this->t('The regex pattern %pattern is not valid.', ['%pattern' => $pattern])
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['field_name']  = trim($form_state->getValue('field_name'));
    $this->configuration['pattern']     = trim($form_state->getValue('pattern'));
    $this->configuration['replacement'] = $form_state->getValue('replacement') ?? '';
  }

  // ---------------------------------------------------------------------------
  // Execution
  // ---------------------------------------------------------------------------

  /**
   * {@inheritdoc}
   */
  public function execute(EntityInterface $entity = NULL): void {
    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }

    $field_name  = $this->configuration['field_name']  ?? '';
    $pattern     = $this->configuration['pattern']     ?? '';
    $replacement = $this->configuration['replacement'] ?? '';

    if ($field_name === '' || $pattern === '') {
      return;
    }

    if (!$entity->hasField($field_name)) {
      return;
    }

    $field = $entity->get($field_name);
    $changed = FALSE;

    foreach ($field as $item) {
      // Determine which property to operate on.
      $property = $this->resolveTextProperty($item);
      $original = $item->get($property)->getValue();

      if (!is_string($original) || $original === '') {
        continue;
      }

      // preg_replace() returns NULL on error, or the original string when
      // there is no match — so we compare before/after to detect a real match.
      $updated = preg_replace($pattern, $replacement, $original);

      if ($updated === NULL || $updated === $original) {
        // No match or regex error — silently skip this item.
        continue;
      }

      $item->get($property)->setValue($updated);
      $changed = TRUE;
    }

    if ($changed) {
      $entity->save();
    }
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Returns the main text-carrying property name for a field item.
   *
   * Most text fields expose their value via "value"; the core "title" field
   * (StringItem) also uses "value".  Falls back to "value" for unknown types.
   */
  private function resolveTextProperty(\Drupal\Core\Field\FieldItemInterface $item): string {
    // getDataDefinition()->getPropertyDefinitions() lists what's available.
    $properties = array_keys($item->getProperties());
    // Prefer "value" if present; otherwise take the first string property.
    if (in_array('value', $properties, TRUE)) {
      return 'value';
    }
    return $properties[0] ?? 'value';
  }

}
