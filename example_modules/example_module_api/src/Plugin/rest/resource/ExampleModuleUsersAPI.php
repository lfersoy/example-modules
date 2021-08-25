<?php

namespace Drupal\example_module_api\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Drupal\Core\Database\Connection;

/**
 * Provides a resource for users management.
 *
 * @RestResource(
 *   id = "users_api",
 *   label = @Translation("Users API Crud."),
 *   uri_paths = {
 *     "canonical" : "/example-crud/data",
 *     "https://www.drupal.org/link-relations/create" = "/example-crud/data"
 *   }
 * )
 */
class ExampleModuleUsersAPI extends ResourceBase {
  
  /**
   * A connection instance.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, 
    array $configuration,
    $plugin_id,
    $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('database')
    );
  }

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Database\Connection $connection
   *   A connection instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    Connection $connection) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->connection = $connection;
  }

  /**
   * Responds to GET requests (Users View).
   *
   * @return \Drupal\rest\ResourceResponse
   *   Returning rest resource.
   */
  public function get() {
    $response = [
      'data' => [],
      'status' => false,
      'message' => 'Users Not Found.'
    ];
    $query = $this->connection->select('example_users', 'users');
    $query->fields('users', ['tid','name','age','type','state']);
    $users = $query->execute()->fetchAll();

    if (!empty($users)) {
      foreach ($users as $user) {
        $response['data'][] = [
          'username' => $user->name,
          'target_id' => $user->tid,
          'date_birth' => !empty($user->age) ? date('Y-m-d', $user->age) : '',
          'type' => $user->type,
          'state' => $user->state,
        ];
      }
      $response['status'] = true;
      $response['message'] = 'Successful.';
    }

    $build = array(
      '#cache' => array(
        'max-age' => 0,
      ),
    );
    return (new ResourceResponse($response))->addCacheableDependency($build);
  }

  /**
   * Responds to POST requests (User Creation).
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function post($data) {
    // Check request body.
    if (isset($data['target_id'], $data['username'])) {
      // Check user.
      if ($this->checkUserByTid($data['target_id'])) {
        throw new BadRequestHttpException('Bad Request. User already exists.');
      }elseif (!ctype_digit($data['target_id'])) {
        throw new BadRequestHttpException('Bad Request. Target id can only contain numbers.');
      }elseif (strlen($data['target_id']) > 10) {
        throw new BadRequestHttpException("Bad Request. Target id {$data['target_id']} it's not allowed.");
      }
      // Check date format.
      if (!empty($data['date_birth']) && 
        !$this->checkDateFormat($data['date_birth'], 'Y-m-d')) {
        throw new BadRequestHttpException('Bad Request. Incorrect date format (Y-m-d).');
      }
      // Check user type.
      if (!empty($data['type']) &&
        !in_array($data['type'], ['Administrador','Webmaster','Desarrollador'])) {
        throw new BadRequestHttpException("Bad Request. Incorrect user type ('Administrador', 'Webmaster', 'Desarrollador').");
      }
      $result = $this->connection->insert('example_users')
        ->fields([
          'name' => $data['username'],
          'tid'  => $data['target_id'],
          'age'  => (!empty($data['date_birth'])) ? strtotime($data['date_birth']) : '',
          'type' => $data['type'],
          'state'=> ($data['type'] == 'Administrador') ? 1 : 0,
        ])
        ->execute();

      $response = [
        'message' => "The user {$data['username']} was created successfully.", 
        'status' => true
      ];

      $build = array(
        '#cache' => array(
          'max-age' => 0,
        ),
      );
      return (new ResourceResponse($response))->addCacheableDependency($build);
    }else{
      throw new BadRequestHttpException("Bad Request. 'target_id' & 'username' are required.");
    }
  }

  /**
   * Responds to PUT requests (User Update).
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function put($data) {
    // Check request body.
    if (isset($data['target_id'])) {
      $fieldsToUpdate = [];
      // Check user.
      if (!$this->checkUserByTid($data['target_id'])) {
        throw new BadRequestHttpException("Bad Request. User don't exists.");
      }
      // Check date format.
      if (isset($data['date_birth'])) {

        if (!$this->checkDateFormat($data['date_birth'], 'Y-m-d')) {
          throw new BadRequestHttpException('Bad Request. Incorrect date format (Y-m-d).');
        }else{
          $fieldsToUpdate['age'] = strtotime($data['date_birth']);
        }
      }
      // Check user type.
      if (isset($data['type'])) {

        if (!in_array($data['type'], ['Administrador','Webmaster','Desarrollador'])) {
          throw new BadRequestHttpException("Bad Request. Incorrect user type ('Administrador', 'Webmaster', 'Desarrollador').");
        }else{
          $fieldsToUpdate['type'] = $data['type'];
          $fieldsToUpdate['state'] = ($data['type'] == 'Administrador') ? 1 : 0;
        } 
      }
      // Check user name.
      if (isset($data['username'])) {
        $fieldsToUpdate['name'] = $data['username'];
      }
      // Process.
      if (!empty($fieldsToUpdate)) {
        $result = $this->connection->update('example_users')
          ->fields($fieldsToUpdate)
          ->condition('tid', $data['target_id'])
          ->execute();
      }else{
        throw new BadRequestHttpException("Bad Request. Send at least one parameter for update.");
      }

      $response = [
        'message' => "The user {$data['username']} was updated successfully.", 
        'status' => true
      ];

      $build = array(
        '#cache' => array(
          'max-age' => 0,
        ),
      );
      return (new ResourceResponse($response))->addCacheableDependency($build);
    }else{
      throw new BadRequestHttpException("Bad Request. 'target_id' is required.");
    }
  }

  /**
   * Check if user exists.
   *
   * @return boolean
   * 
   * @param string $utid
   *   User Target Id.
   */
  public function checkUserByTid($utid) {
    // Check user id.
    $query = $this->connection->select('example_users', 'users');
    $query->fields('users', ['tid']);
    $query->condition('users.tid', $utid);
    $query->range(0, 1);

    if (!empty($query->execute()->fetchField())) {
      return true;
    }
    return false;
  }

  /**
   * Check date format.
   *
   * @return boolean
   * 
   * @param string $cDate
   *  date
   * @param string $format
   *  format
   */
  public function checkDateFormat($date, $format) {
    $formated = \DateTime::createFromFormat($format, $date);
    
    if ($formated && $formated->format($format) === $date)
      return true;

    return false;
  }
}
