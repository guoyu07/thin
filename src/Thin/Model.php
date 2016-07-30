<?php

/*
 * Created Datetime:2016-7-30 9:10:43
 * Creator:Jimmy Jaw <web3d@live.cn>
 * Copyright:TimeCheer Inc. 2016-7-30 
 * 
 */

namespace Thin;

/**
 * 基础数据模型,包含下面的基本功能：
 * 属性声明：定义这个模型的属性/字段
 * 属性标签：每个属性都可以关联一个标签用来显示
 * 属性批赋值：一次性完成多模型属性赋值
 * 基于场景的数据验证
 * 
 * 基本用法举例:
 * class User extends Model
 * {
 *      const LOGIN = 'login';
 *      const REG = 'reg';
 * 
 *      protected $fields = ['id', 'username', 'email', 'age'];
 * 
 *      protected $scenarios = [
 *          self::LOGIN => ['username', 'password'],    // 如果是登陆就验证用户名和密码
 *          self::REG => ['username', 'email', 'password'],    // 如果是注册就需要用户名、邮箱和密码
 *      ];
 * 
 *      protected $rules = [
 *          [$fields, $rule, $message, $condition, $type, $when, $params],
 *          ...
 *      ];
 * 
 *      
 * }
 */
class Model
{
    // 操作场景

    /**
     * 默认场景
     */
    const BOTH = 3;
    
    /**
     * 插入场景
     */
    const INSERT = 1;

    /**
     * 更新场景
     */
    const UPDATE = 2;
    
    /**
     * 必须验证
     */
    const MUST_VALIDATE = 1;
    
    /**
     * 表单存在字段则验证
     */
    const EXISTS_VALIDATE = 0;
    
    /**
     * 表单值不为空则验证
     */
    const VALUE_VALIDATE = 2;

    /**
     *
     * @var string 设置当前场景
     */

    protected $scenario = self::BOTH;

    /**
     *
     * @var string 模型名称
     */
    protected $name = '';

    /**
     *
     * @var array 模型属性 字段信息
     */
    protected $fields = [];

    /**
     *
     * @var array 定义每个场景下对应的字段清单,基本格式如:
     * <code>
     * [
     *     self::LOGIN => ['username', 'password'],    // 如果是登陆就验证用户名和密码
     *     self::REG => ['username', 'email', 'password'],    // 如果是注册就需要用户名、邮箱和密码
     * ]
     * </code>
     * 用法:
     * <code>
     * $user = new User();
     * if ($user->setData($_POST, self::LOGIN)->validate()) {....}
     * </code>
     */
    protected $scenarios = [];

    /**
     *
     * @var array 数据信息
     */
    protected $data = [];

    /**
     *
     * @var array 数据验证规则定义
     * [[field,rule,message,condition,type,when,params], ...]
     */
    protected $rules = [];

    /**
     *
     * @var array 自动填充某些字段值
     * [
     *      ['field', '填充内容', '填充场景(选填)', '附加规则(选填)', 额外参数(选填)]
     * ]
     * 填充场景默认为 新增
     * 附加规则 默认为 string
     */
    protected $auto = [];

    /**
     *
     * @var bool 是否批处理验证
     */
    protected $validateAll = false;

    /**
     *
     * @var bool 是否自动检测数据表字段信息
     */
    protected $autoCheckFields = true;

    /**
     *
     * @var array 最近错误信息
     */
    protected $error = [];

    public function __construct($name = '')
    {
        $this->init();

        // 获取模型名称
        if (!empty($name)) {
            $this->name = $name;
        } elseif (empty($this->name)) {
            $this->name = $this->getName();
        }
    }

    /**
     * 供模型子类进行初始化操作
     */
    protected function init()
    {
        //...
    }

    /**
     * 得到当前的数据对象名称
     * @return string
     */
    public function getName()
    {
        if (empty($this->name)) {
            $this->name = get_class($this);
        }
        return $this->name;
    }

    /**
     * 获取所有字段
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * 指定场景设置数据对象值,会覆盖原有值
     * 开启autoCheckFields,会根据场景自动过滤字段;同时,触发自动填充字段规则
     * @param mixed $data 数据 可传递数组或对象
     * @param string $scenario 指定当前场景
     * @return $this
     * @throws \Exception
     */
    public function setData($data = [], $scenario = self::BOTH)
    {
        if (is_object($data)) {
            $data = get_object_vars($data);
        } elseif (!is_array($data)) {
            throw new \Exception('_DATA_TYPE_INVALID_');
        }

        if ($scenario != self::BOTH && !in_array($scenario, array_keys($this->scenarios))) {
            throw new \Exception('_SCENARIO_INVALID_');
        }
        $this->scenario = $scenario;

        // 过滤非法字段数据
        if ($this->autoCheckFields) {
            $fields = ($scenario != self::BOTH) ? $this->scenarios[$scenario] : $this->getFields();
            foreach ($data as $key => $val) {
                if (!in_array($key, $fields)) {
                    unset($data[$key]);
                } elseif (MAGIC_QUOTES_GPC && is_string($val)) {
                    $data[$key] = stripslashes($val);
                }
            }
        }

        // 创建完成对数据进行自动处理
        $this->fillFields($data, $scenario);

        $this->data = $data;

        return $this;
    }

    /**
     * 返回模型的错误信息
     * @return array
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * 自动表单令牌验证,依赖session
     * 防止重复提交或跨站提交
     * @param string $name token键名
     * @param array $data 待校验的数据
     * @param bool $reset 校验后是否充值session中的token值
     * @return bool
     */
    private function checkToken($name, array $data, $reset = false)
    {
        if (!isset($data[$name]) || !isset($_SESSION[$name])) { // 令牌数据无效
            return false;
        }

        // 令牌验证
        list($key, $value) = explode('_', $data[$name]);
        if (isset($_SESSION[$name][$key]) && $value && $_SESSION[$name][$key] === $value) {
            unset($_SESSION[$name][$key]); // 验证成功销毁session
            return true;
        }

        // 校验失败时是否重置
        if ($reset) {
            unset($_SESSION[$name][$key]);
        }
        return false;
    }

    /**
     * 自动数据填充
     * @param array $data 创建数据
     * @param string $scenario 创建类型
     */
    private function fillFields(&$data, $scenario)
    {
        if (empty($this->auto)) {
            return ;
        }
        
        foreach ($this->auto as $auto) {
            // 填充因子定义格式
            // array('field','填充内容','填充条件','附加规则',[额外参数])
            if (empty($auto[2]))
                $auto[2] = self::INSERT; // 默认为新增的时候自动填充
            if ($scenario == $auto[2] || $auto[2] == self::BOTH) {
                if (empty($auto[3]))
                    $auto[3] = 'string';
                switch (trim($auto[3])) {
                    case 'function':    //  使用函数进行填充 字段的值作为参数
                    case 'callback': // 使用回调方法
                        $args = isset($auto[4]) ? (array) $auto[4] : array();
                        if (isset($data[$auto[0]])) {
                            array_unshift($args, $data[$auto[0]]);
                        }
                        if ('function' == $auto[3]) {
                            $data[$auto[0]] = call_user_func_array($auto[1], $args);
                        } else {
                            $data[$auto[0]] = call_user_func_array(array(&$this, $auto[1]), $args);
                        }
                        break;
                    case 'field':    // 用其它字段的值进行填充
                        $data[$auto[0]] = $data[$auto[1]];
                        break;
                    case 'ignore': // 为空忽略
                        if ($auto[1] === $data[$auto[0]])
                            unset($data[$auto[0]]);
                        break;
                    case 'string':
                    default: // 默认作为字符串填充
                        $data[$auto[0]] = $auto[1];
                }
                if (isset($data[$auto[0]]) && false === $data[$auto[0]])
                    unset($data[$auto[0]]);
            }
        }

    }

    /**
     * 添加一条验证规则
     * @param string $fields 一条规则可能包含多个字段
     * @param mixed $rule 校验规则
     * @param string $message
     * @param int $condition MUST_VALIDATE|VALUE_VALIDATE|EXISTS_VALIDATE
     * @param string $type 规则类型
     * @param int $when 场景 1 - INSERT 2 - UPDATE 3 - BOTH
     * @param array $params 函数或方法校验时传递的参数
     */
    public function addRule($fields, $rule, $message = '', $condition = 0, $type = 'regex', $when = 0, $params = [])
    {
        $this->rules[] = [$fields, $rule, $message, $condition, $type, $when, $params];
    }

    /**
     * 数据验证,含表单令牌验证,在setData方法后调用
     * @param array $data 创建数据
     * @return boolean
     */
    public function validate($data)
    {
        // 表单令牌验证
        if (C('TOKEN_ON')) {
            $token_name = C('TOKEN_NAME', null, '__hash__');
            if (!$this->checkToken($token_name , $data, C('TOKEN_RESET'))) {
                $this->error[$token_name][] = '_TOKEN_ERROR_';
                return false;
            }
        }

        if (empty($this->rules)) {
            return true;
        }

        if ($this->validateAll) { // 重置验证错误信息
            $this->error = [];
        }
        foreach ($this->rules as $val) {
            // 验证因子定义格式
            // array(field,rule,message,condition,type,when,params)
            // 判断是否需要执行验证
            if (empty($val[5]) || $val[5] == $this->scenario) {
                if (0 == strpos($val[2], '{%') && strpos($val[2], '}')) {
                    // 支持提示信息的多语言 使用 {%语言定义} 方式
                    $val[2] = L(substr($val[2], 2, -1));
                }
                $val[3] = isset($val[3]) ? $val[3] : self::EXISTS_VALIDATE;
                $val[4] = isset($val[4]) ? $val[4] : 'regex';

                if (false === $this->validateField($data, $val))
                    return false;
            }
        }
        // 批量验证的时候最后返回错误
        if (!empty($this->error)) {
            return false;
        }

        return true;
    }

    /**
     * 验证表单字段 支持批量验证
     * 如果批量验证返回错误的数组信息
     * @param array $data 创建数据
     * @param array $val 验证因子
     * @return boolean
     */
    protected function validateField($data, $val)
    {
        $can_val = false;
        if (self::MUST_VALIDATE == $val[3]) {//必须验证 不管表单是否有设置该字段
            $can_val = true;
        } elseif (self::VALUE_VALIDATE == $val[3]) {//值不为空的时候才验证
            if ('' != trim($data[$val[0]])) {
                $can_val = true;
            }
        } else {// 默认表单存在该字段就验证
            if (isset($data[$val[0]])) {
                $can_val = true;
            }
        }

        if (!$can_val) {
            return;
        }

        if ($this->validateAll && isset($this->error[$val[0]]))
            return; //当前字段已经有规则验证没有通过
        if (false === $this->valField($data, $val)) {
            $this->error[$val[0]][] = $val[2];
            if (!$this->validateAll) {
                return false;
            }
        }
        return;
    }

    /**
     * 根据验证因子验证单个字段
     * @param array $data 创建数据
     * @param array $val 验证因子
     * @return boolean
     */
    protected function valField($data, $val)
    {
        switch (strtolower(trim($val[4]))) {
            case 'function':// 使用函数进行验证
            case 'callback':// 调用方法进行验证
                $args = isset($val[6]) ? (array) $val[6] : array();
                if (is_string($val[0]) && strpos($val[0], ','))
                    $val[0] = explode(',', $val[0]);
                if (is_array($val[0])) {
                    // 支持多个字段验证
                    foreach ($val[0] as $field)
                        $_data[$field] = $data[$field];
                    array_unshift($args, $_data);
                } else {
                    array_unshift($args, $data[$val[0]]);
                }

                return call_user_func_array($val[1], $args);
            case 'confirm': // 验证两个字段是否相同
                return $data[$val[0]] == $data[$val[1]];
            default:  // 检查附加规则
                return Validator::check($data[$val[0]], $val[1], $val[4]);
        }
    }

    /**
     * 设置数据对象的值
     * @param string $name 名称
     * @param mixed $value 值
     */
    public function __set($name, $value)
    {
        // 设置数据对象属性
        $this->data[$name] = $value;
    }

    /**
     * 获取数据对象的值
     * @param string $name 名称
     * @return mixed
     */
    public function __get($name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    /**
     * 检测数据对象的值
     * @param string $name 名称
     * @return boolean
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    /**
     * 销毁数据对象的值
     * @param string $name 名称
     */
    public function __unset($name)
    {
        unset($this->data[$name]);
    }

    protected function returnResult($data, $type = '')
    {
        if ($type) {
            if (is_callable($type)) {
                return call_user_func($type, $data);
            }
            switch (strtolower($type)) {
                case 'json':
                    return json_encode($data);
                case 'xml':
                    return xml_encode($data);
            }
        }
        return $data;
    }

}
