<?php

namespace Drupal\example_module_users\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;


/**
 *  Example Module - Users Form.
 */
class ExampleModuleUserForm extends FormBase {

  /**
   * @var Connection $connection
   */
  protected $connection;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   A connection instance.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(){
    return 'example_module_users_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames(){
    return [
      'example_module_users.users_form',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state){
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nombre'),
      '#required' => TRUE,
      '#rules' => array('alpha_numeric'),
    ];
    $form['tid'] = [
      '#type' => 'number',
      '#title' => $this->t('Identificación'),
      '#required' => TRUE,
      '#rules' => array('numeric'),
    ];
    $form['age'] = [
      '#type' => 'date',
      '#title' => $this->t('Fecha de nacimiento'),
      '#default_value' => '',
    ];
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Cargo'),
      '#options' => [
        'Administrador' => 'Administrador',
        'Webmaster' => 'Webmaster', 
        'Desarrollador' => 'Desarrollador'
      ],
      '#required' => FALSE,
      '#maxlength' => 20,
      '#default_value' => '',
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#default_value' => $this->t('Enviar')
    ];
    $form['#theme'] = 'example_module_users_form';
    return $form;
  }

  /**
  * {@inheritdoc}
  */
  public function validateForm(array &$form, FormStateInterface $form_state){
    $values = $form_state->getValues();
    // Check user id.
    $query = $this->connection->select('example_users', 'users');
    $query->fields('users', ['tid']);
    $query->condition('users.tid', $values['tid']);
    $query->range(0, 1);

    if (!empty($query->execute()->fetchField())) {
      $form_state->setErrorByName('tid', $this->t("El usuario con identificación {$values['tid']} ya se encuentra registrado."));
    }
  }

  /**
  * {@inheritdoc}
  */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $result = $this->connection->insert('example_users')
      ->fields([
        'name' => $values['name'],
        'tid'  => $values['tid'],
        'age'  => (!empty($values['age'])) ? strtotime($values['age']) : '',
        'type' => $values['type'],
        'state'=> ($values['type'] == 'Administrador') ? 1 : 0,
      ])
      ->execute();
    drupal_set_message($this->t('El usuario %user se guardó correctamente.', [
      '%user' => $values['name'],
    ]));
  }
}
