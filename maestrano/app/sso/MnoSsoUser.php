<?php

/**
 * Configure App specific behavior for 
 * Maestrano SSO
 */
class MnoSsoUser extends MnoSsoBaseUser
{
  /**
   * Database connection
   * @var PDO
   */
  public $connection = null;
  
  
  /**
   * Extend constructor to inialize app specific objects
   *
   * @param OneLogin_Saml_Response $saml_response
   *   A SamlResponse object from Maestrano containing details
   *   about the user being authenticated
   */
  public function __construct(OneLogin_Saml_Response $saml_response, &$session = array(), $opts = array())
  {
    // Call Parent
    parent::__construct($saml_response,$session);
    
    // Assign new attributes
    //$this->connection = $opts['db_connection'];
  }
  
  
  /**
   * Sign the user in the application. 
   * Parent method deals with putting the mno_uid, 
   * mno_session and mno_session_recheck in session.
   *
   * @return boolean whether the user was successfully set in session or not
   */
  protected function setInSession()
  {
    global $user;
    $user = db_query("SELECT * FROM users WHERE uid = :uid", array(':uid' => $this->local_id))->fetchObject();
    error_log("In setInSession: " . $user->uid);
    
    if ($user) {
        // Function uses $user as global variable
        $form_state['uid'] = $user->uid;
        
        user_login_finalize($form_state);
        //user_login_submit(array(), $form_state);
        //var_dump($_SESSION);
        //flush();
        
        return true;
    } else {
        return false;
    }
  }
  
  
  /**
   * Used by createLocalUserOrDenyAccess to create a local user 
   * based on the sso user.
   * If the method returns null then access is denied
   *
   * @return the ID of the user created, null otherwise
   */
  protected function createLocalUser()
  {
    $lid = null;
    
    if ($this->accessScope() == 'private') {
      // First build user
      $user_hash = $this->buildLocalUser();
      
      // Create user
      $user = user_save('',$user_hash);
      
      $lid = $user->uid;
    }
    
    return $lid;
  }
  
  /**
   * Build a local user for creation
   *
   * @return the ID of the user created, null otherwise
   */
  protected function buildLocalUser()
  {
    $password = $this->generatePassword();
    
    $user = Array(
      'name'     => $this->formatUniqueUsername(),
      'mail'     => $this->email,
      'pass'     => Array('pass1' => $password, 'pass2' => $password),
      'status'   => 1,
      'roles'    => $this->getRolesToAssign()
    );
    
    return $user;
  }
  
  /**
   * Return a unique username which is more user friendly
   * that just using the maestrano uid
   */
  public function formatUniqueUsername() {
    $s_name = preg_replace("/[^a-zA-Z0-9]+/", "", $this->name);
    $s_surname = preg_replace("/[^a-zA-Z0-9]+/", "", $this->surname);
    $formatted = $s_name . '_' . $s_surname . '_' . $this->uid;
    return $formatted;
  }
  
  /**
   * Return the rolse to give to the user based on context
   * If the user is the owner of the app or at least Admin
   * for each organization, then it is given the role of 'Admin'.
   * Return 'User' role otherwise
   *
   * @return the ID of the user created, null otherwise
   */
  public function getRolesToAssign() {
    $roles = [2]; // User
    
    if ($this->app_owner) {
      $roles = [2,3]; // Admin
    } else {
      foreach ($this->organizations as $organization) {
        if ($organization['role'] == 'Admin' || $organization['role'] == 'Super Admin') {
          $roles = [2,3]; // Admin
        } else {
          $roles = [2]; // User
        }
      }
    }
    
    return $roles;
  }
  
  /**
   * Get the ID of a local user via Maestrano UID lookup
   *
   * @return a user ID if found, null otherwise
   */
  protected function getLocalIdByUid()
  {
    $user = db_query("SELECT uid FROM users WHERE mno_uid = :uid", array(':uid' => $this->uid))->fetchObject();
    
    if ($user && $user->uid) {
      return intval($user->uid);
    }
    
    return null;
  }
  
  /**
   * Get the ID of a local user via email lookup
   *
   * @return a user ID if found, null otherwise
   */
  protected function getLocalIdByEmail()
  {
    $user = db_query("SELECT uid FROM users WHERE mail = :email", array(':email' => $this->email))->fetchObject();
    
    if ($user && $user->uid) {
      return intval($user->uid);
    }
    
    return null;
  }
  
  /**
   * Set all 'soft' details on the user (like name, surname, email)
   * Implementing this method is optional.
   *
   * @return boolean whether the user was synced or not
   */
   protected function syncLocalDetails()
   {
     if($this->local_id) {
       $upd = db_update('users')
         ->fields(array(
          'name' => $this->formatUniqueUsername(),
          'mail' => $this->email
        ))
        ->condition('uid', $this->local_id)
        ->execute();
       
       return $upd;
     }
     
     return false;
   }
  
  /**
   * Set the Maestrano UID on a local user via id lookup
   *
   * @return a user ID if found, null otherwise
   */
  protected function setLocalUid()
  {
    if($this->local_id) {
      $upd = db_update('users')
        ->fields(array(
         'mno_uid' => $this->uid
       ))
       ->condition('uid', $this->local_id)
       ->execute();
      
      return $upd;
    }
    
    return false;
  }
  
  
}