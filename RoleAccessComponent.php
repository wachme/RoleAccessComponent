<?php
App::uses('Component', 'Controller');

/**
 * RoleAccess Component
 * 
 * Component that makes role-based access easy to use in your controller class.
 * 
 * To specify settings for action, simply add to its comment block:
 * @role.<role-name>[.<param>] <value> [<comment>]
 * 
 * Example:
 * @role.admin allow Allow admin access the action
 * @role.user deny Deny user access the action
 * @role.user.access deny The same as above
 * @role.user allow Overwrites the above setting
 * @role.public deny Deny from all unregistered users ('public' is predefined role name)
 * 
 * Example:
 * @role.admin.action admin_index Redirect admin to other action
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
    /**
     * Parameters of actions assigned at runtime.
     * 
     * @var array
     */
    protected $_params = array();
    
    public $components = array('Session', 'Auth');

    /**
     * Component settings:
     * 
     * - 'roleField' - User model's field storing role name
     * - 'Roles' - Defines inheritance of roles
     *   Example:
     *   'Roles' => array(
     *      'user' => array(
     *         'subscriber',  // 'subscriber' inherits from 'user'
     *         'editor' => array(
     *           'author' => array(
     *             'moderator' => array(
     *               'admin' => 'superadmin' // 'admin' extends moderator privileges
     *             )
     *           )
     *         )
     *       )
     *   )
     */
    public $settings = array(
        'roleField' => 'role'
    );

    /**
     * Returns full path to the specified role name.
     * 
     * @param array $set Set within to search
     * @param string $name Role name
     * @param callback $cmp Optional comparison function
     * @return array|false Ordered array representing path or false if not found
     */
    protected function _findRole(array $set, $name, $cmp='strcasecmp') {
        foreach($set as $k => $v) {
            if(is_string($k) && is_string($v) && $cmp($v, $name) === 0) {
                return array($k, $v);
            }
            elseif(is_string($k) && $cmp($k, $name) === 0) {
                return array($k);
            }
            elseif(is_string($v) && $cmp($v, $name) === 0) {
                return array($v);
            }
            elseif(is_array($v) && ($sub = $this->_findRole($v, $name)) !== false) {
                return array_merge(array($k), $sub);
            }
        }
        return false;
    }

    /**
     * Get the current user's role name, 'public' if user isn't logged in.
     * 
     * @return string
     */
    public function getRoleName() {
        $role = $this->Auth->user($this->settings['roleField']);
        return $role !== null ? $role : 'public';
    }

    /**
     * Returns full path to the current user's role.
     * 
     * @param string $roleName Optional role name
     * @return array Ordered array representing path
     */
    public function getRole($roleName=null) {
        $roleName = $roleName !== null ? $roleName : $this->getRoleName();
        if($roleName === 'public') {
            return array('public');
        }
        if(($role = $this->_findRole($this->settings['Roles'], $roleName)) !== false) {
            return $role;
        }
        return array($roleName);
    }

    /**
     * Get parameters assigned to the specified action.
     * 
     * @param string $action
     * @param string $roleName Optional role name
     * @return array
     */
    public function getParams($action, $roleName=null) {
        $ref = new ReflectionMethod($this->_controller, $action);
        $doc = $ref->getDocComment();
        preg_match_all('/^\s*\*\s*\@role\.' . ($roleName === null ? '(?P<role>[^\.\s]+)' : $roleName)
            . '\.?(?(?<=\.)(?P<param>.*?))[^\S\n]+(?P<value>.+?)\s/im',
            $doc, $out, PREG_SET_ORDER);

        $params = array();
        foreach($out as $i) {
            $param = empty($i['param']) ? 'access' : $i['param'];

            if($roleName === null) {
                $params[$i['role']][$param] = $i['value'];
            }
            else {
                $params[$param] = $i['value'];
            }
        }
        if($roleName === null && isset($this->_params[$action])) {
            foreach($this->_params[$action] as $role => $p) {
                if(isset($params[$role])) {
                    $params[$role] = array_merge($params[$role], $p);
                }
                else {
                    $params[$role] = $p;
                }
            }
        }
        elseif($roleName !== null && isset($this->_params[$action][$roleName])) {
            $params = array_merge($params, $this->_params[$action][$roleName]);
        }
        return $params;
    }

    /**
     * Set access to action for a given role.
     * 
     * @param string $action
     * @param string $roleName
     * @param string $access allow|deny
     * @return void
     */
    public function setAccess($action, $roleName, $access) {
        $this->_params[$action][$roleName]['access'] = $access;
    }
    
    /**
     * Redirect to other action for a given role.
     * 
     * @param string $action
     * @param string $roleName
     * @param string $destAction Destination action
     * @return void
     */
    public function setAction($action, $roleName, $destAction) {
        $this->_params[$action][$roleName]['action'] = $destAction;
    }
    
    /**
     * Dispatch controller's action using role parameters.
     * 
     * @param $action
     * @return void
     */
    public function dispatch($action) {
        $role = $this->getRole();
        print_r($this->getParams($action));
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