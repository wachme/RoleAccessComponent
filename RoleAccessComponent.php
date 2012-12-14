<?php
App::uses('Component', 'Controller');

class RoleNotFoundException extends CakeException {
    protected $_messageTemplate = 'Role %s not found';
}

/**
 * RoleAccess Component
 * 
 * Component that makes role-based access easy to use in your controller class.
 * 
 * To specify settings for action, simply add to its comment block:
 * @role.<role-name> <value> [<comment>]
 * 
 * Example:
 * @role.admin allow Allow admin access the action
 * @role.user deny Deny user access the action
 * @role.user allow Overwrites the above setting
 * @role.public deny Deny from all unregistered users ('public' is a predefined role name)
 * 
 * Example:
 * @role.admin role_admin_index Redirect admin to other action ('role_admin_index').
 * Remember to use prefix for every action that should be invoked only by redirection.
 * 
 * @author Karol Wach
 */
class RoleAccessComponent extends Component {
    /**
     * Current controller.
     * 
     * @var Controller
     */
    protected $_controller;
    
    public $components = array('Session', 'Auth');

    /**
     * Component settings:
     * 
     * - 'roleField' - User model's field storing role name
     * - 'actionPrefix' - Prefix for actions that should be invoked only by redirection
     * - 'roles' - Existing roles
     * Example:
     * 'roles' => array(
     *   'user',
     *   'author' => 'user',  // author extends user privileges
     *   'editor' => 'author,  // editor extends author privileges
     *   'admin' => 'editor',  // ...
     *   'super-admin' => 'admin'
     * )
     */
    public $settings = array(
        'roleField' => 'role',
        'actionPrefix' => 'role_'
    );

    /**
     * Returns raw parameters assigned to the specified action.
     * 
     * @param string $action
     * @param string $roleName Optional role name
     * @return array
     */
    protected function _parseParams($action) {
        $ref = new ReflectionMethod($this->_controller, $action);
        $doc = $ref->getDocComment();
        preg_match_all('/^\s*\*\s*\@role\.(?P<role>\S+)[^\S\n]+(?P<value>\S+)/im',
            $doc, $out, PREG_SET_ORDER);

        $params = array();
        foreach($out as $i) {
            $params[$i['role']] = $i['value'];
        }
        return $params;
    }

    /**
     * Returns role's parent (empty string if parent is not defined) or false if not found.
     * 
     * @param string $roleName
     * @return string|booleab
     */
    protected function _findRole($roleName) {
        if(isset($this->settings['roles'][$roleName])) {
            return $this->settings['roles'][$roleName];
        }
        if($roleName == 'public' || is_int(array_search($roleName, $this->settings['roles']))) {
            return '';
        }
        return false;
    }

    /** 
     * Returns value assigned to the specified role.
     * 
     * @param string $roleName
     * @param array $params Parsed action parameters
     * @return string|null
     */
    protected function _getValue($roleName, $params) {
        $role = $this->_findRole($roleName);
        if($role === false) {
            throw new RoleNotFoundException(array('roleName' => $roleName));
        }
        $value = isset($params[$roleName]) ? $params[$roleName] : null;
        if($value === null && is_string($role) && !empty($role)) {
            $extends = $role;
            if(($inherited = $this->_getValue($extends, $params)) !== null) {
                $value = $inherited;
            }
        }
        return $value;
    }

    /**
     * Get the current user's role, 'public' if user isn't logged in.
     * 
     * @return string
     */
    public function getRoleName() {
        $role = $this->Auth->user($this->settings['roleField']);
        return $role !== null ? $role : 'public';
    }
    
    /**
     * Get value for the specified action and role.
     * 
     * @param string $action
     * @param string $roleName
     * @return string|null
     */
    public function getValue($action, $roleName) {
        return $this->_getValue($roleName, $this->_parseParams($action));
    }
    
    /**
     * Dispatch controller's action using role configuration.
     * 
     * @param string $action
     * @return void
     */
    public function dispatch($action) {
        if(strpos($action, $this->settings['actionPrefix']) === 0) {
            throw new PrivateActionException(array(
                'controller' => $this->_controller->name,
                'action' => $action
            ));
        }
        $role = $this->getRoleName();
        $value = $this->getValue($action, $role);
        if(is_string($value)) {
            if($value == 'allow') {
                $this->Auth->allow();
            }
            elseif($value == 'deny') {
                $this->Auth->deny();
            }
            elseif(method_exists($this->_controller, $value)) {
                // $this->_controller->setAction($value) doesn't work correctly - invokes action twice (CakePHP v2.2.2)
                $this->_controller->request->params['action'] = $value;
                $this->_controller->view = $value;
            }
            else {
                throw new MissingActionException(array(
                    'controller' => $this->_controller->name,
                    'action' => $value 
                ));
            }
        }
    }
    
    public function __construct(ComponentCollection $collection, $settings = array()) {
        parent::__construct($collection, $settings + $this->settings);
        $this->_controller = $collection->getController();
    }
    
    public function startup(Controller $controller) {
        $this->dispatch($controller->action);
    }
}

?>