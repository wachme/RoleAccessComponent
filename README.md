RoleAccessComponent
===================

CakePHP component that makes role-based access easy to use in your controller class.

How to use it?
==============

Step 1
------
Move RoleAccessComponent.php to `app/Controller/Component` directory in your project.

Step 2
------
Add uses line `App::uses('AppController', 'Controller');` in the beginning of your controller file.

Step 3
------
Add `RoleAccess` to `$components` property of your controller class.

    public $components = array(
        'RoleAccess' => array()
    );
    
Step 4
------
Configure

* **`roleField`**

Specifies field of your `User` model that holds role name. Default value is `role`.

    public $components = array(
        'RoleAccess' => array(
            'roleField' => 'user_role'
        )
    );
    
* **`actionPrefix`**

Specifies method prefix used in your actions that should be invoked only by RoleAccess component.  
Action starting with the prefix cannot be accessible for client directly (by entering its name in URL). Default value is `role_`

    public $components = array(
        'RoleAccess' => array(
            'actionPrefix' => 'my_prefix_'
        )
    );

Then example action should be:

    /**
     * @role.admin my_prefix_admin_index
     */
    public function index() {
        // ...
    }

    public function my_prefix_admin_index() {
        // ...
    }
    
* **`roles`**

Array defining roles. Every role you want to use must be declared here.   
Role can inherit value from other role. `'superadmin' => 'admin'` means that user with role `superadmin` inherits value from the `admin` role.

    public $components = array('RoleAccess' => array(
        'roles' => array(
            'registered',  // simple declaration
            'subscriber' => 'registered',  // inheritance
            'user' => 'subscriber',
            'author' => 'user',
            'editor' => 'user',
            'moderator' => 'user',
            'admin' => 'moderator',
            'superadmin' => 'admin'
        )
    );

Step 5
------
Define rules for your actions.

To specify settings for action, simply add to its comment block:  
`@role.<role-name> <value> [<comment>]`  
where:  
* `<role-name>` is one of the roles declared in `roles` array or `public`.  
  **`public`** is a predefined role name representing not logged in users.
* `<value>` can be:
    * `allow` if you want to allow user to access action
    * `deny` denies access for user
    * any other string - redirects user to other action.  
    **Remember to use prefix for every action that should be invoked only by redirection.**

### Examples:

    /**
     * @role.public deny Deny access for not logged in clients
     * @role.user allow Only users can view this action
     * @role.admin role_admin_account Redirect admin
     */
    public function account() {
        // edit user account
    }
    
    public function role_admin_account($user_id=null) {
        // edit other user account
    }
