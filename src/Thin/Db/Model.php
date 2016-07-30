<?php

/*
 * Created Datetime:2016-7-30 9:11:36
 * Creator:Jimmy Jaw <web3d@live.cn>
 * Copyright:TimeCheer Inc. 2016-7-30 
 * 
 */

namespace Thin\Db;

use Thin\Model as BaseModel;

use Think\Db;

/**
 * Db单表Model
 * 特性:
 * 单表增删改查
 */
class Model extends BaseModel
{

    /**
     *
     * @var \Think\Db\Driver\Mysql 当前数据库操作对象
     */
    protected $db = null;

    /**
     *
     * @var array 数据库对象池
     */
    private $_db = array();

    /**
     *
     * @var string 主键名称
     */
    protected $pk = 'id';

    /**
     *
     * @var bool 主键是否自动增长
     */
    protected $autoinc = false;

    /**
     *
     * @var string 数据表前缀
     */
    protected $tablePrefix = null;

    /**
     *
     * @var string 数据库名称
     */
    protected $dbName = '';

    /**
     * @var string 数据库配置
     */
    protected $connection = '';

    /**
     *
     * @var string 数据表名（不包含表前缀）
     */
    protected $tableName = '';

    /**
     *
     * @var string 实际数据表名（包含表前缀）
     */
    protected $trueTableName = '';
    
    /**
     *
     * @var array 命名范围定义
     */
    protected $_scope = array();
    
    /**
     *
     * @var array 链操作方法列表
     */
    protected $methods = array('strict', 'order', 'alias', 'having', 'group', 'lock', 'distinct', 'auto', 'filter', 'validate', 'result', 'token', 'index', 'force');

    /**
     * 架构函数
     * 取得DB类的实例对象 字段检查
     * @param string $name 模型名称
     */
    public function __construct($name = '')
    {
        parent::__construct($name);

        // 设置表前缀
        if (null == $this->tablePrefix) {
            $this->tablePrefix = C('DB_PREFIX');
        }

        // 数据库初始化操作
        // 获取数据库操作对象
        // 当前模型有独立的数据库连接信息
        $this->db(0, $this->connection, true);
    }

    /**
     * 自动检测数据表信息
     * @access protected
     * @return void
     */
    protected function _checkTableInfo()
    {
        // 如果不是Model类 自动记录数据表信息
        // 只在第一次执行记录
        if (empty($this->fields)) {
            // 如果数据表字段没有定义则自动获取
            if (C('DB_FIELDS_CACHE')) {
                $db = $this->dbName? : C('DB_NAME');
                $fields = F('_fields/' . strtolower($db . '.' . $this->tablePrefix . $this->name));
                if ($fields) {
                    $this->fields = $fields;
                    if (!empty($fields['_pk'])) {
                        $this->pk = $fields['_pk'];
                    }
                    return;
                }
            }
            // 每次都会读取数据表信息
            $this->flush();
        }
    }

    /**
     * 获取字段信息并缓存
     * @access public
     * @return void
     */
    public function flush()
    {
        // 缓存不存在则查询数据表信息
        $this->db->setModel($this->name);
        $fields = $this->db->getFields($this->getTableName());
        if (!$fields) { // 无法获取字段信息
            return false;
        }
        $this->fields = array_keys($fields);
        unset($this->fields['_pk']);
        foreach ($fields as $key => $val) {
            // 记录字段类型
            $type[$key] = $val['type'];
            if ($val['primary']) {
                // 增加复合主键支持
                if (isset($this->fields['_pk']) && $this->fields['_pk'] != null) {
                    if (is_string($this->fields['_pk'])) {
                        $this->pk = array($this->fields['_pk']);
                        $this->fields['_pk'] = $this->pk;
                    }
                    $this->pk[] = $key;
                    $this->fields['_pk'][] = $key;
                } else {
                    $this->pk = $key;
                    $this->fields['_pk'] = $key;
                }
                if ($val['autoinc'])
                    $this->autoinc = true;
            }
        }
        // 记录字段类型信息
        $this->fields['_type'] = $type;

        // 2008-3-7 增加缓存开关控制
        if (C('DB_FIELDS_CACHE')) {
            // 永久缓存数据表信息
            $db = $this->dbName? : C('DB_NAME');
            F('_fields/' . strtolower($db . '.' . $this->tablePrefix . $this->name), $this->fields);
        }
    }

    /**
     * 数据读取后的处理
     * @access protected
     * @param array $data 当前数据
     * @return array
     */
    protected function _read_data($data)
    {
        // 检查字段映射
        if (!empty($this->_map) && C('READ_DATA_MAP')) {
            foreach ($this->_map as $key => $val) {
                if (isset($data[$val])) {
                    $data[$key] = $data[$val];
                    unset($data[$val]);
                }
            }
        }
        return $data;
    }

    /**
     * 切换当前的数据库连接
     * @access public
     * @param integer $linkNum  连接序号
     * @param mixed $config  数据库连接信息
     * @param boolean $force 强制重新连接
     * @return Model
     */
    public function db($linkNum = '', $config = '', $force = false)
    {
        if ('' === $linkNum && $this->db) {
            return $this->db;
        }

        if (!isset($this->_db[$linkNum]) || $force) {
            // 创建一个新的实例
            if (!empty($config) && is_string($config) && false === strpos($config, '/')) { // 支持读取配置参数
                $config = C($config);
            }
            $this->_db[$linkNum] = Db::getInstance($config);
        } elseif (NULL === $config) {
            $this->_db[$linkNum]->close(); // 关闭数据库连接
            unset($this->_db[$linkNum]);
            return;
        }

        // 切换数据库连接
        $this->db = $this->_db[$linkNum];
        $this->_after_db();
        // 字段检测
        if (!empty($this->name) && $this->autoCheckFields)
            $this->_checkTableInfo();
        return $this;
    }

    // 数据库切换后回调方法
    protected function _after_db()
    {
        
    }

    /**
     * 得到完整的数据表名
     * @access public
     * @return string
     */
    public function getTableName()
    {
        if (empty($this->trueTableName)) {
            $tableName = !empty($this->tablePrefix) ? $this->tablePrefix : '';
            if (!empty($this->tableName)) {
                $tableName .= $this->tableName;
            } else {
                $tableName .= parse_name($this->name);
            }
            $this->trueTableName = strtolower($tableName);
        }
        return (!empty($this->dbName) ? $this->dbName . '.' : '') . $this->trueTableName;
    }

    /**
     * 返回数据库的错误信息
     * @access public
     * @return string
     */
    public function getDbError()
    {
        return $this->db->getError();
    }

    /**
     * 返回最后插入的ID
     * @access public
     * @return string
     */
    public function getLastInsID()
    {
        return $this->db->getLastInsID();
    }

    /**
     * 返回最后执行的sql语句
     * @access public
     * @return string
     */
    public function getLastSql()
    {
        return $this->db->getLastSql($this->name);
    }

    /**
     * 获取主键名称
     * @access public
     * @return string
     */
    public function getPk()
    {
        return $this->pk;
    }
    
    /**
     * 获取所有字段
     * @return array
     */
    public function getFields()
    {
        $fields = parent::getFields();
        unset($fields['_type'], $fields['_pk']);
        
        return $fields;
    }
    
    /**
     * 根据验证因子验证单个字段 增加 unique
     * @param array $data 创建数据
     * @param array $val 验证因子
     * @return boolean
     */
    protected function valField($data, $val)
    {
        switch (strtolower(trim($val[4]))) {
            case 'unique': // 验证某个值是否唯一
                if (is_string($val[0]) && strpos($val[0], ','))
                    $val[0] = explode(',', $val[0]);
                $map = array();
                if (is_array($val[0])) {
                    // 支持多个字段验证
                    foreach ($val[0] as $field)
                        $map[$field] = $data[$field];
                } else {
                    $map[$val[0]] = $data[$val[0]];
                }
                $pk = $this->getPk();
                if (!empty($data[$pk]) && is_string($pk)) { // 完善编辑的时候验证唯一
                    $map[$pk] = array('neq', $data[$pk]);
                }
                if ($this->where($map)->find())
                    return false;
                return true;
            default :
                return parent::valField($data, $val);
        }
    }
    
    /**
     * 对要保存到数据库的数据进行处理
     * 主要是对字段类型做校验并根据需要应用过滤策略
     * @param mixed $data 要操作的数据
     * @return boolean
     */
    protected function _facade($data)
    {
        // 检查数据字段合法性
        if (!empty($this->fields)) {
            $fields = $this->fields;
            foreach ($data as $key => $val) {
                if (!in_array($key, $fields, true)) {
                    if (!empty($this->options['strict'])) {
                        E(L('_DATA_TYPE_INVALID_') . ':[' . $key . '=>' . $val . ']');
                    }
                    unset($data[$key]);
                } elseif (is_scalar($val)) {
                    // 字段类型检查 和 强制转换
                    $this->_parseType($data, $key);
                }
            }
        }

        // 安全过滤
        if (!empty($this->options['filter'])) {
            $data = array_map($this->options['filter'], $data);
            unset($this->options['filter']);
        }
        $this->_before_write($data);
        return $data;
    }

    // 写入数据前的回调方法 包括新增和更新
    protected function _before_write(&$data)
    {
        
    }
    
    /**
     * 数据类型检测
     * @param mixed $data 数据
     * @param string $key 字段名
     */
    protected function _parseType(&$data, $key)
    {
        if (!isset($this->options['bind'][':' . $key]) && isset($this->fields['_type'][$key])) {
            $fieldType = strtolower($this->fields['_type'][$key]);
            if (false !== strpos($fieldType, 'enum')) {
                // 支持ENUM类型优先检测
            } elseif (false === strpos($fieldType, 'bigint') && false !== strpos($fieldType, 'int')) {
                $data[$key] = intval($data[$key]);
            } elseif (false !== strpos($fieldType, 'float') || false !== strpos($fieldType, 'double')) {
                $data[$key] = floatval($data[$key]);
            } elseif (false !== strpos($fieldType, 'bool')) {
                $data[$key] = (bool) $data[$key];
            }
        }
    }

}
